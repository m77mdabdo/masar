<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IntelligenceItem;
use App\Models\SourceItem;
use Illuminate\Console\Command;

/**
 * Enforces both retention windows.
 *
 * Retention that depends on somebody remembering is not retention. Two separate
 * rules run here:
 *
 *   - A publisher's body text is deleted after `raw_body_days`, whatever the
 *     item's review state. The processed record — our own summary, the link,
 *     the date — survives, which is why the two were split into two tables.
 *   - A dismissed item is deleted after `dismissed_days`, so an editor who
 *     rejects the wrong thing has a month to notice and not forever.
 */
class PurgeIntelligence extends Command
{
    protected $signature = 'masar:purge-intelligence {--dry-run : Report what would be purged and change nothing}';

    protected $description = 'Delete ingested body text and dismissed items past their retention windows';

    public function handle(): int
    {
        $bodyDays = (int) config('masar.intelligence.retention.raw_body_days', 30);
        $dismissedDays = (int) config('masar.intelligence.retention.dismissed_days', 30);

        $staleBodies = SourceItem::query()
            ->whereNotNull('raw_body')
            ->where('fetched_at', '<=', now()->subDays($bodyDays));

        $dismissed = IntelligenceItem::query()->purgeable();

        $bodyCount = (clone $staleBodies)->count();
        $dismissedCount = (clone $dismissed)->count();

        if ($this->option('dry-run')) {
            $this->table(
                ['What', 'Rows', 'Older than'],
                [
                    ['ingested body text', $bodyCount, $bodyDays.' days'],
                    ['dismissed items', $dismissedCount, $dismissedDays.' days'],
                ],
            );

            return self::SUCCESS;
        }

        // Nulled rather than deleted: the item itself is still evidence that we
        // saw the story, and that record is ours.
        $staleBodies->update(['raw_body' => null]);

        // Chunked so a backlog cannot build one enormous delete.
        $dismissed->chunkById(500, fn ($items) => $items->each->delete());

        $this->info("Purged {$bodyCount} body texts and {$dismissedCount} dismissed items.");

        return self::SUCCESS;
    }
}
