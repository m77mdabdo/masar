<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Article;
use Illuminate\Support\Collection;

/**
 * What to read next.
 *
 * Editor-curated links come first and are never displaced — an editor who chose
 * three follow-ups meant those three. Topic overlap only fills what is left, so
 * the rail is never empty on a story nobody curated.
 */
class RelatedArticlesQuery
{
    public function __invoke(Article $article, int $limit = 4): Collection
    {
        $curated = $article->related()
            ->published()
            ->with(['category.translations', 'author:id,name', 'heroMedia'])
            ->take($limit)
            ->get();

        if ($curated->count() >= $limit) {
            return $curated;
        }

        return $curated->concat($this->byTopic($article, $limit - $curated->count(), $curated));
    }

    /**
     * @param  Collection<int, Article>  $exclude
     * @return Collection<int, Article>
     */
    private function byTopic(Article $article, int $limit, Collection $exclude): Collection
    {
        $topicIds = $article->relationLoaded('topics')
            ? $article->topics->pluck('id')
            : $article->topics()->pluck('topics.id');

        return PublishedArticlesQuery::make()
            ->forLocale($article->locale)
            ->excluding([$article->getKey(), ...$exclude->pluck('id')->all()])
            ->latest()
            ->builder()
            ->when(
                $topicIds->isNotEmpty(),
                fn ($query) => $query->whereHas('topics', fn ($t) => $t->whereIn('topics.id', $topicIds->all())),
            )
            ->limit($limit)
            ->get();
    }
}
