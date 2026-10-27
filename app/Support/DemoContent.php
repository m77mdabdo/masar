<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Article;
use App\Models\Company;
use App\Models\Media;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Marks what demo seeding creates, so it can all be removed.
 *
 * Inside `record()`, every model using IsDemoContent is created with
 * `is_demo = true`, and so is every activity-log entry. That covers rows
 * written through Eloquent. Rows written another way — a bulk insert, a
 * relation an Action writes without events — are caught by the sweep at the
 * end of the outermost `record()`, which flags anything hanging off a demo row.
 *
 * The exit is `masar:purge-demo`. Demo rows sit in a database that later
 * becomes production; nothing is created here that the purge cannot find.
 */
final class DemoContent
{
    private static int $depth = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function record(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;

            if (self::$depth === 0) {
                self::flagDependents();
            }
        }
    }

    public static function isRecording(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Rows that belong to a demo row are demo, however they were written.
     */
    public static function flagDependents(): void
    {
        $demoArticles = DB::table('articles')->where('is_demo', true)->select('id');

        DB::table('article_blocks')->whereIn('article_id', $demoArticles)->update(['is_demo' => true]);
        DB::table('article_sources')->whereIn('article_id', $demoArticles)->update(['is_demo' => true]);

        foreach (self::graphModels() as $class) {
            $alias = (new $class)->getMorphClass();
            $ids = DB::table((new $class)->getTable())->where('is_demo', true)->select('id');

            DB::table('entity_mentions')
                ->where(fn ($q) => $q->where('mentionable_type', $alias)->whereIn('mentionable_id', $ids))
                ->orWhere(fn ($q) => $q->where('entity_type', $alias)->whereIn('entity_id', $ids))
                ->update(['is_demo' => true]);

            DB::table((new Media)->getTable())
                ->where('model_type', $alias)
                ->whereIn('model_id', $ids)
                ->update(['is_demo' => true]);
        }
    }

    /**
     * Demo-flagged models that can sit on either end of an entity mention or
     * own media.
     *
     * @return array<int, class-string>
     */
    public static function graphModels(): array
    {
        return [Article::class, Opportunity::class, Company::class, Person::class, Topic::class];
    }
}
