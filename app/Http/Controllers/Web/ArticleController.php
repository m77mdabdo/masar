<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Article;
use App\Models\Category;
use App\Models\Topic;
use App\Queries\RelatedArticlesQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ArticleController
{
    public function __invoke(
        string $locale,
        string $category,
        string $slug,
        RelatedArticlesQuery $related,
    ): View {
        $article = Article::query()
            ->published()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with([
                'category.translations', 'author', 'heroMedia', 'blocks',
                'sources', 'topics.translations', 'factChecker',
            ])
            ->first();

        // A mismatched category is a different URL for the same content, and a
        // duplicate that competes with the canonical in search results.
        if ($article === null || $article->category?->slug !== $category) {
            throw new NotFoundHttpException;
        }

        return view('pages.article', [
            'article' => $article,
            'related' => $related($article),
            ...$this->rail($locale, $article),
            'companies' => $article->mentions()
                ->where('entity_type', 'company')
                ->with('entity.translations')
                ->orderByDesc('prominence')
                ->get()
                ->map(fn ($mention) => $mention->entity)
                ->filter()
                ->values(),
        ]);
    }

    /**
     * The end rail: an explore card, trending topics, and the sponsored slot.
     *
     * None of it is specific to this article beyond the category, so it is
     * cached per category and per locale rather than queried on every article
     * view — three queries that would otherwise repeat on every page of the
     * site's largest template.
     *
     * @return array<string, mixed>
     */
    private function rail(string $locale, Article $article): array
    {
        $categoryId = (int) $article->category_id;

        return Cache::tags(['articles', "locale:{$locale}"])->remember(
            "article:rail:{$locale}:{$categoryId}",
            (int) config('masar.cache.homepage'),
            fn (): array => [
                // The explore card points somewhere other than where the reader
                // already is; a card offering the page they are on is furniture.
                'exploreCategory' => Category::query()
                    ->with('translations')
                    ->whereKeyNot($categoryId)
                    ->orderBy('sort_order')
                    ->first(),

                // The explore card carries a photograph. It comes from the
                // newest article in that category rather than from the category
                // itself, which has no media of its own.
                'exploreMedia' => Article::query()
                    ->published()
                    ->forLocale($locale)
                    ->whereKeyNot($article->getKey())
                    ->whereNotNull('hero_media_id')
                    ->whereHas('category', fn ($query) => $query->whereKeyNot($categoryId))
                    ->latest('published_at')
                    ->with('heroMedia')
                    ->first()?->heroMedia,

                'trendingTopics' => Topic::query()
                    ->with('translations')
                    ->orderByDesc('articles_count')
                    ->limit(8)
                    ->get(),

                // A real sponsored article, never a placeholder: an empty slot
                // is honest and a fake advertiser is not.
                'sponsored' => Article::query()
                    ->published()
                    ->forLocale($locale)
                    ->where('is_sponsored', true)
                    ->whereKeyNot($article->getKey())
                    ->with('category.translations')
                    ->latest('published_at')
                    ->first(),
            ],
        );
    }
}
