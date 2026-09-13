<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Enums\DuplicateStage;
use App\Models\IntelligenceItem;
use App\Models\SourceItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Four checks, cheapest first, stopping at the first match.
 *
 * The first three are identities: the same URL, the same canonical URL, the
 * same publisher id. The fourth is a resemblance, and that difference is why
 * nothing here discards anything. A near-match is *flagged* and put in front of
 * an editor, because a second development on a story is very often the actual
 * news, and a deduplicator that swallows it is worse than none.
 */
final class Deduper
{
    public function __construct(private readonly SimHash $simhash) {}

    /**
     * @return array{item: IntelligenceItem, stage: DuplicateStage}|null
     */
    public function find(SourceItem $item): ?array
    {
        foreach (['url', 'canonicalUrl', 'externalId', 'title'] as $stage) {
            $match = $this->{$stage}($item);

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /** @return array{item: IntelligenceItem, stage: DuplicateStage}|null */
    private function url(SourceItem $item): ?array
    {
        $match = $this->base($item)
            ->whereHas('sourceItem', fn ($q) => $q->where('url_hash', $item->url_hash))
            ->first();

        return $match ? ['item' => $match, 'stage' => DuplicateStage::Url] : null;
    }

    /** @return array{item: IntelligenceItem, stage: DuplicateStage}|null */
    private function canonicalUrl(SourceItem $item): ?array
    {
        if ($item->canonical_hash === null) {
            return null;
        }

        // Matched against either side: a publisher's canonical is often another
        // publisher's plain URL.
        $match = $this->base($item)
            ->whereHas('sourceItem', fn ($q) => $q
                ->where('canonical_hash', $item->canonical_hash)
                ->orWhere('url_hash', $item->canonical_hash))
            ->first();

        return $match ? ['item' => $match, 'stage' => DuplicateStage::CanonicalUrl] : null;
    }

    /** @return array{item: IntelligenceItem, stage: DuplicateStage}|null */
    private function externalId(SourceItem $item): ?array
    {
        if (blank($item->external_id)) {
            return null;
        }

        // Scoped to the source: two publishers' ids share a namespace by
        // accident, never by meaning.
        $match = $this->base($item)
            ->where('source_id', $item->source_id)
            ->whereHas('sourceItem', fn ($q) => $q->where('external_id', $item->external_id))
            ->first();

        return $match ? ['item' => $match, 'stage' => DuplicateStage::ExternalId] : null;
    }

    /** @return array{item: IntelligenceItem, stage: DuplicateStage}|null */
    private function title(SourceItem $item): ?array
    {
        if ($item->title_simhash === null) {
            return null;
        }

        $max = (int) config('masar.intelligence.dedup.simhash_max_distance', 16);

        // Hamming distance is not expressible in SQL, so the window is narrowed
        // in SQL first and compared in memory — CLAUDE.md §5. The window is
        // hours, not rows, so this stays bounded as volume grows.
        $candidates = $this->base($item)
            ->with('sourceItem:id,title_simhash')
            ->whereHas('sourceItem', fn ($q) => $q->whereNotNull('title_simhash'))
            ->get();

        foreach ($candidates as $candidate) {
            $distance = $this->simhash->distance($item->title_simhash, $candidate->sourceItem?->title_simhash);

            if ($distance <= $max) {
                return ['item' => $candidate, 'stage' => DuplicateStage::Title];
            }
        }

        return null;
    }

    /**
     * Items recent enough to be the same story. A headline recurring months
     * later is a new story, not a duplicate.
     *
     * @return Builder<IntelligenceItem>
     */
    private function base(SourceItem $item): Builder
    {
        $hours = (int) config('masar.intelligence.dedup.window_hours', 72);

        return IntelligenceItem::query()
            ->where('detected_at', '>=', now()->subHours($hours))
            ->when($item->id !== null, fn ($q) => $q->where('source_item_id', '!=', $item->id))
            ->orderByDesc('detected_at');
    }
}
