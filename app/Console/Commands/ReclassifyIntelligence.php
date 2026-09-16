<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IntelligenceItem;
use App\Services\Intelligence\Classifier;
use App\Services\Intelligence\ImportanceScorer;
use Illuminate\Console\Command;

/**
 * Re-derives classification and importance for stored inbox items.
 *
 * Classification happens once, at ingest. So the keyword table and the document
 * taxonomy are frozen into every row the moment it arrives, and changing either
 * leaves the whole inbox showing the old vintage — as happened when
 * `market_move`, `forecast` and `statement` were added and 114 stored items
 * still carried the taxonomy that had no such forms. `masar:recount` exists for
 * exactly this shape of staleness on the publish gate; this is its counterpart.
 *
 * It reads `source_items.raw_title` and `raw_summary`, which is what the
 * classifier saw at ingest — not the cleaned copy on the intelligence item.
 * Comparing against the wrong input is how a clean re-derivation looks like
 * five regressions.
 *
 * Nothing here touches review state. An editor's judgement is theirs; only the
 * machine's reading of an item is recomputed.
 */
class ReclassifyIntelligence extends Command
{
    protected $signature = 'masar:reclassify
        {--dry-run : Report the drift without writing anything}
        {--source= : Limit to one source id}';

    protected $description = 'Re-derive document type, classification path and importance for stored inbox items';

    public function handle(Classifier $classifier, ImportanceScorer $scorer): int
    {
        $dry = (bool) $this->option('dry-run');

        $query = IntelligenceItem::query()
            ->with(['source', 'sourceItem'])
            ->when($this->option('source'), fn ($q) => $q->where('source_id', $this->option('source')));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No items to reclassify.');

            return self::SUCCESS;
        }

        $seen = 0;
        $typeChanged = 0;
        $pathChanged = 0;
        $scoreChanged = 0;
        $moves = [];

        $bar = $this->output->createProgressBar($total);

        foreach ($query->cursor() as $item) {
            $bar->advance();

            /*
             * Always present: `source_item_id` is NOT NULL and the foreign key
             * cascades, and retention nulls `raw_body` rather than deleting the
             * row. So a purged item still has its title and summary, which is
             * all the classifier reads — reclassification stays valid for the
             * life of the item.
             */
            $raw = $item->sourceItem;
            $seen++;

            $class = $classifier->classify((string) $raw->raw_title, $raw->raw_summary, $item->source);
            $score = $scorer->score((string) $raw->raw_title, $raw->raw_summary, $item->source, $class['type'], $raw->published_at);

            $wasType = $item->document_type?->value;
            $wasPath = $item->classification_path?->value;
            $wasScore = $item->importance;

            if ($wasType !== $class['type']->value) {
                $typeChanged++;
                $key = ($wasType ?? '-').' -> '.$class['type']->value;
                $moves[$key] = ($moves[$key] ?? 0) + 1;
            }

            if ($wasPath !== $class['path']->value) {
                $pathChanged++;
            }

            if ($wasScore !== $score['score']) {
                $scoreChanged++;
            }

            if (! $dry) {
                $item->forceFill([
                    'document_type' => $class['type'],
                    'classification_path' => $class['path'],
                    'classification_evidence' => $class['evidence'],
                    'importance' => $score['score'],
                    'importance_base' => $score['base'],
                    'importance_inputs' => $score['inputs'],
                ])->save();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', ''], [
            ['items read', $seen],
            ['document type changed', $typeChanged],
            ['classification path changed', $pathChanged],
            ['importance changed', $scoreChanged],
        ]);

        if ($moves !== []) {
            arsort($moves);
            $this->line('Type movements:');
            foreach (array_slice($moves, 0, 12, true) as $move => $count) {
                $this->line(sprintf('  %4d  %s', $count, $move));
            }
            $this->newLine();
        }

        if ($dry) {
            $this->warn('Dry run — nothing written.');

            return self::SUCCESS;
        }

        $this->info('Reclassified. Review state and editor judgement were not touched.');

        return self::SUCCESS;
    }
}
