<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticlePublished;
use App\Events\ArticleUnpublished;
use App\Models\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates every cached surface an article appears on.
 *
 * Queued: a publish must not wait on cache invalidation, and a Redis hiccup
 * must not fail the publish request. Worst case the reader sees a stale list
 * for a few seconds.
 */
class FlushArticleCaches implements ShouldQueue
{
    public function handle(ArticlePublished|ArticleUnpublished $event): void
    {
        $article = $event->article;

        foreach ($this->tagsFor($article) as $tag) {
            Cache::tags($tag)->flush();
        }
    }

    /**
     * @return array<int, string>
     */
    private function tagsFor(Article $article): array
    {
        $tags = [
            'homepage',
            'articles',
            'article:'.$article->getKey(),
            'locale:'.$article->locale,
        ];

        if ($article->category_id !== null) {
            $tags[] = 'category:'.$article->category_id;
        }

        foreach ($article->topics()->pluck('topics.id') as $topicId) {
            $tags[] = 'topic:'.$topicId;
        }

        // Entity profile pages list this article too.
        foreach ($article->mentions()->get(['entity_type', 'entity_id']) as $mention) {
            $tags[] = $mention->entity_type.':'.$mention->entity_id;
        }

        return array_values(array_unique($tags));
    }
}
