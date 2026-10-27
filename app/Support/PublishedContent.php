<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One definition of "published", shared by every denormalised counter.
 *
 * CLAUDE.md §5: denormalised counters count published rows only. Two actions
 * implemented that rule separately and disagreed — `SyncArticleTopics` checked
 * status, soft-delete and a `published_at` in the past, while
 * `SyncArticleEntities` checked nothing at all and counted drafts as coverage.
 * Every company in the demo data was inflated.
 *
 * So the predicate lives here rather than in either of them. If the rule ever
 * changes — scheduled articles counting, say — it changes once and both
 * counters move together instead of one being quietly left behind.
 */
final class PublishedContent
{
    /**
     * A live article: published, not in the future, not deleted.
     *
     * @param  string  $table  the articles table or its alias in the query
     * @return array{0: string, 1: array<int, string>}  [sql, bindings]
     */
    public static function articleCondition(string $table = 'articles'): array
    {
        $sql = "`{$table}`.`status` = ?
                and `{$table}`.`deleted_at` is null
                and `{$table}`.`published_at` is not null
                and `{$table}`.`published_at` <= ?";

        return [$sql, ['published', now()->toDateTimeString()]];
    }

    /**
     * A live opportunity. It has no status column and no soft delete — its
     * whole publication state is the date.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function opportunityCondition(string $table = 'opportunities'): array
    {
        $sql = "`{$table}`.`published_at` is not null
                and `{$table}`.`published_at` <= ?";

        return [$sql, [now()->toDateTimeString()]];
    }
}
