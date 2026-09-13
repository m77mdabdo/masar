<?php

declare(strict_types=1);

namespace App\Actions\Intelligence;

use App\Enums\ReviewState;
use App\Models\IntelligenceItem;
use App\Models\Source;
use App\Models\SourceItem;
use App\Services\Intelligence\Classifier;
use App\Services\Intelligence\Deduper;
use App\Services\Intelligence\ImportanceScorer;
use App\Services\Intelligence\ParsedItem;
use App\Services\Intelligence\SimHash;
use App\Services\Intelligence\UrlNormaliser;
use Illuminate\Support\Facades\DB;

/**
 * One parsed entry becomes a raw record and an inbox row.
 *
 * The legal rule is enforced here and only here, so no fetcher has to know it:
 * a publisher's body text is stored only where that source's `legal_mode`
 * permits it, and the summary an editor reads is ours in every case.
 */
class StoreSourceItem
{
    public function __construct(
        private readonly UrlNormaliser $urls,
        private readonly SimHash $simhash,
        private readonly Deduper $deduper,
        private readonly Classifier $classifier,
        private readonly ImportanceScorer $scorer,
    ) {}

    public function __invoke(Source $source, ParsedItem $parsed): ?IntelligenceItem
    {
        $urlHash = $this->urls->hash($parsed->url);

        if ($urlHash === null) {
            return null;
        }

        return DB::transaction(function () use ($source, $parsed, $urlHash): ?IntelligenceItem {
            // Cheapest possible exit: we already hold this exact URL for this
            // source, so there is nothing to parse, score or compare.
            $existing = SourceItem::query()
                ->where('source_id', $source->getKey())
                ->where('url_hash', $urlHash)
                ->first();

            if ($existing !== null) {
                return null;
            }

            $item = SourceItem::query()->create([
                'source_id' => $source->getKey(),
                'external_id' => $parsed->externalId,
                'url' => $parsed->url,
                'canonical_url' => $parsed->canonicalUrl,
                'raw_title' => mb_substr($parsed->title, 0, 512),
                'raw_summary' => $parsed->summary,
                // The whole legal rule, in one expression.
                'raw_body' => $source->legal_mode->retainsBody() ? $parsed->body : null,
                'author' => $parsed->author,
                'published_at' => $parsed->publishedAt,
                'fetched_at' => now(),
                'url_hash' => $urlHash,
                'canonical_hash' => $this->urls->hash($parsed->canonicalUrl),
                'title_simhash' => ($this->simhash)($parsed->title),
                'payload' => $parsed->payload,
            ]);

            $duplicate = $this->deduper->find($item);

            $classification = $this->classifier->classify($parsed->title, $parsed->summary, $source);

            $importance = $this->scorer->score(
                $parsed->title,
                $parsed->summary,
                $source,
                $classification['type'],
                $parsed->publishedAt,
            );

            return IntelligenceItem::query()->create([
                'source_item_id' => $item->getKey(),
                'source_id' => $source->getKey(),
                'title' => mb_substr($parsed->title, 0, 512),
                // Ours. Where the source permits no retention there is no
                // publisher summary to fall back on, and that is the point.
                'summary' => $source->legal_mode->retainsBody() ? $parsed->summary : null,
                'url' => $parsed->url,
                'published_at' => $parsed->publishedAt,
                'detected_at' => now(),
                'locale' => $source->locale,
                'document_type' => $classification['type'],
                'classification_path' => $classification['path'],
                'classification_evidence' => $classification['evidence'],
                'importance' => $importance['score'],
                'importance_inputs' => $importance['inputs'],
                'review_state' => ReviewState::New,
                // Flagged, never discarded: a second development on the same
                // story is often the actual news, and that is the editor's call.
                // `?? null` on an array offset of null still dereferences it.
                // The explicit check is the one that actually guards.
                'duplicate_of_id' => $duplicate === null ? null : $duplicate['item']->getKey(),
                'duplicate_stage' => $duplicate === null ? null : $duplicate['stage'],
            ]);
        });
    }
}
