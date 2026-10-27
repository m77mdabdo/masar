<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AdCreative;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Picks a creative for a placement and counts that it was shown.
 *
 * Two things here are deliberate.
 *
 * **Impressions are buffered in Redis, never written per request.** CLAUDE.md §5
 * forbids `increment()` on every request for exactly this shape of counter, and
 * an ad slot is on more pages than an article is. `flush()` moves the buffer
 * into the table; its scheduled command was removed with ads' launch scope
 * (DEFERRED.md) and returns with them.
 *
 * **A failure to count is never a failure to render, and a failure to render is
 * never a failure of the page.** An advertisement is the least important thing
 * on any page it appears on; if Redis is unreachable or the creative is
 * malformed, the reader gets the article and we lose a number.
 */
final class AdServer
{
    private const BUFFER = 'ads:impressions';

    public function forPlacement(string $placementKey): ?AdCreative
    {
        try {
            return AdCreative::query()
                ->servable()
                ->whereHas('placement', fn ($q) => $q->where('key', $placementKey))
                ->with('campaign:id,advertiser')
                // No targeting and no weighting: one slot, whatever is running.
                // Random so two creatives in one placement share it evenly.
                ->inRandomOrder()
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function recordImpression(AdCreative $creative): void
    {
        try {
            Redis::connection()->hincrby(self::BUFFER, (string) $creative->getKey(), 1);
        } catch (Throwable) {
            // Counting is not worth an error page.
        }
    }

    /**
     * Move the buffer into the table. Returns how many creatives were updated.
     *
     * Read-and-delete per field rather than read-then-clear-all, so impressions
     * counted while this runs are not thrown away.
     */
    public function flushImpressions(): int
    {
        $redis = Redis::connection();
        $buffered = (array) $redis->hgetall(self::BUFFER);
        $updated = 0;

        foreach ($buffered as $id => $count) {
            $taken = (int) $redis->hget(self::BUFFER, (string) $id);

            if ($taken <= 0) {
                continue;
            }

            $redis->hincrby(self::BUFFER, (string) $id, -$taken);

            $updated += AdCreative::query()->whereKey($id)->update([
                'impressions' => \Illuminate\Support\Facades\DB::raw("impressions + {$taken}"),
            ]);
        }

        return $updated;
    }
}
