<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Counts that an article was read. Really counts it.
 *
 * `views_count` was read by two surfaces and written by nothing, so «الأكثر
 * قراءة» ranked on seeded numbers. This is the implementation, built on the
 * pattern the ad counter already uses.
 *
 * Four rules, each of which is a way the naive version would be wrong:
 *
 * - **Buffered in Redis, never `increment()` per request** (§5). An article page
 *   is the most-hit route we have.
 * - **Once per visitor per article.** A refresh is not a read. Keyed on the
 *   first-party `visitor_id` cookie that already exists for the
 *   returning-audience measure, so this needs no new identifier.
 * - **Bots are not readers.** A crawler fetching a real article is not a 404 and
 *   would otherwise rank it.
 * - **Failure is silent.** A counting error must never reach the page, exactly
 *   as an advertisement must not.
 */
final class ViewCounter
{
    private const BUFFER = 'views:buffer';

    private const SEEN = 'views:seen';

    public function record(Article $article, Request $request): void
    {
        try {
            if ($this->isBot((string) $request->userAgent())) {
                return;
            }

            $visitor = (string) ($request->attributes->get('visitor_id') ?? '');

            if ($visitor === '') {
                // No cookie yet means we cannot tell a refresh from a read, and
                // counting anyway would let one visitor rank a story alone.
                return;
            }

            $seen = self::SEEN.':'.$article->getKey().':'.$visitor;
            $redis = Redis::connection();

            // SET NX EX: the first read wins and sets the window atomically, so
            // two concurrent requests from one visitor count once.
            if (! $redis->set($seen, '1', 'EX', $this->dedupeSeconds(), 'NX')) {
                return;
            }

            $redis->hincrby(self::BUFFER, $this->bufferField($article), 1);
        } catch (Throwable) {
            // A number is not worth an error page.
        }
    }

    /**
     * Move the buffer into `article_views` and the lifetime total.
     *
     * Read-and-decrement per field rather than clearing the hash, so reads
     * counted while this runs survive.
     */
    public function flush(): int
    {
        $redis = Redis::connection();
        $buffered = (array) $redis->hgetall(self::BUFFER);
        $written = 0;

        foreach (array_keys($buffered) as $field) {
            $taken = (int) $redis->hget(self::BUFFER, (string) $field);

            if ($taken <= 0) {
                continue;
            }

            [$articleId, $day] = array_pad(explode(':', (string) $field, 2), 2, null);

            if (! ctype_digit((string) $articleId) || $day === null) {
                $redis->hdel(self::BUFFER, (string) $field);

                continue;
            }

            /*
             * An article can disappear between the read and the flush — deleted
             * by an editor, or in development simply belonging to another
             * database, since Redis is shared and the test suite uses the real
             * connection.
             *
             * Without this the foreign key throws and the *whole* flush dies,
             * so one missing row costs every article's counts, on every run,
             * until someone clears the buffer by hand. Drop the orphan and keep
             * going.
             */
            if (! Article::query()->whereKey((int) $articleId)->exists()) {
                $redis->hdel(self::BUFFER, (string) $field);

                continue;
            }

            $redis->hincrby(self::BUFFER, (string) $field, -$taken);

            DB::table('article_views')->upsert(
                [[
                    'article_id' => (int) $articleId,
                    'viewed_on' => $day,
                    'views' => $taken,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['article_id', 'viewed_on'],
                // Add rather than replace: a later flush on the same day must
                // accumulate, not overwrite.
                ['views' => DB::raw('article_views.views + '.$taken), 'updated_at' => now()],
            );

            Article::query()->whereKey((int) $articleId)
                ->update(['views_count' => DB::raw("views_count + {$taken}")]);

            $written++;
        }

        return $written;
    }

    /** Whether any real readership has been recorded at all. */
    public function hasData(): bool
    {
        return DB::table('article_views')->exists();
    }

    private function bufferField(Article $article): string
    {
        return $article->getKey().':'.Carbon::now()->toDateString();
    }

    private function dedupeSeconds(): int
    {
        return max(60, (int) config('masar.views.dedupe_hours', 24) * 3600);
    }

    private function isBot(string $agent): bool
    {
        if ($agent === '') {
            return true; // No user agent at all is not a reader.
        }

        foreach ((array) config('masar.views.ignore_agents', []) as $pattern) {
            if (preg_match((string) $pattern, $agent) === 1) {
                return true;
            }
        }

        return false;
    }
}
