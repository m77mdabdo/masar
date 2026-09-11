<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Models\Article;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Topic tagging plus the `topics.articles_count` counter.
 *
 * Counted articles are published ones only: a topic page advertising "12
 * articles" and then rendering three because the rest are drafts is worse than
 * no count at all.
 */
class SyncArticleTopics
{
    /**
     * @param  array<int, int>  $topicIds
     */
    public function __invoke(Article $article, array $topicIds): void
    {
        DB::transaction(function () use ($article, $topicIds): void {
            $before = $article->topics()->pluck('topics.id')->all();

            $article->topics()->sync($topicIds);

            $this->recount(array_unique([...$before, ...$topicIds]));
        });
    }

    /**
     * Recompute counters for every topic whose membership may have changed.
     *
     * @param  array<int, int>  $topicIds
     */
    public function recount(array $topicIds): void
    {
        $topicIds = array_values(array_filter(array_unique($topicIds)));

        if ($topicIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($topicIds), '?'));

        DB::update(
            "update `topics`
             set `articles_count` = (
                 select count(*) from `article_topic`
                 inner join `articles` on `articles`.`id` = `article_topic`.`article_id`
                 where `article_topic`.`topic_id` = `topics`.`id`
                   and `articles`.`status` = ?
                   and `articles`.`deleted_at` is null
                   and `articles`.`published_at` is not null
                   and `articles`.`published_at` <= ?
             )
             where `id` in ({$placeholders})",
            ['published', now()->toDateTimeString(), ...$topicIds],
        );
    }

    /**
     * Rebuild every topic counter. Used by masar:recount.
     */
    public function recountAll(): int
    {
        $ids = Topic::query()->pluck('id')->all();

        $this->recount($ids);

        return count($ids);
    }
}
