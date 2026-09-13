<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Redirects\NormalisePath;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Records a 404 without slowing the 404 down.
 *
 * Queued because a missing page is already a bad experience and must not also be
 * a slow one — and because a scanner sweep would otherwise put its write load on
 * the web workers.
 *
 * Aggregated by path with an upsert: one row per path, `hits` incremented. A row
 * per hit would make the table unreadable within a day of the first bot.
 */
class RecordNotFound implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $path,
        private readonly ?string $referrer = null,
    ) {}

    public static function shouldIgnore(string $path): bool
    {
        if (mb_strlen($path) > (int) config('masar.not_found.max_path_length', 500)) {
            return true;
        }

        foreach ((array) config('masar.not_found.ignore_patterns', []) as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    public function handle(NormalisePath $normalise): void
    {
        $path = $normalise($this->path);

        if ($path === '/' || self::shouldIgnore($path)) {
            return;
        }

        $now = now();

        // Upsert rather than read-then-write: two concurrent 404s on the same
        // path would otherwise race and lose a hit.
        DB::table('not_founds')->upsert(
            [[
                'path' => $path,
                'referrer' => $this->referrer ? mb_substr($this->referrer, 0, 255) : null,
                'hits' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'resolved' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['path'],
            [
                'hits' => DB::raw('hits + 1'),
                'last_seen_at' => $now,
                'updated_at' => $now,
            ],
        );
    }
}
