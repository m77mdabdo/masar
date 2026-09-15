<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticlePublished;
use App\Events\ArticleUnpublished;
use App\Jobs\FlushCacheTags;
use App\Models\Article;

/**
 * Invalidates every cached surface an article appears on.
 *
 * The listener itself runs inline and does almost nothing: it reads the tags
 * off the article — two small queries, while the row is certainly still there —
 * and hands a list of strings to a queued job. The flush is what is slow and
 * what must not fail a publish, so the flush is what is queued.
 *
 * It is deliberately not a queued listener. That shape serialises the Article
 * and re-fetches it in the worker, which turns "the article was deleted" and
 * "the database was rebuilt" into permanently failed jobs — and the usual guard,
 * $deleteWhenMissingModels, is read off Laravel's CallQueuedListener rather than
 * off this class, so it never fires. Computing the tags here means the job can
 * never be orphaned, because there is nothing left in it to orphan.
 */
class FlushArticleCaches
{
    public function handle(ArticlePublished|ArticleUnpublished $event): void
    {
        FlushCacheTags::dispatch($this->tagsFor($event->article));
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
