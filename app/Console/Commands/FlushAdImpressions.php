<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AdServer;
use Illuminate\Console\Command;

/**
 * Moves buffered ad impressions from Redis into the table.
 *
 * The buffer is the live count; the column is the durable one. If Redis is lost
 * between runs the impressions in it are lost with it — recorded in the
 * stale-snapshot inventory rather than pretended otherwise.
 */
class FlushAdImpressions extends Command
{
    protected $signature = 'masar:flush-ad-impressions';

    protected $description = 'Write buffered ad impressions from Redis into ad_creatives';

    public function handle(AdServer $ads): int
    {
        $updated = $ads->flushImpressions();

        $this->info($updated === 0 ? 'No buffered impressions.' : "Updated {$updated} creative(s).");

        return self::SUCCESS;
    }
}
