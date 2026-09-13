<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Topic;
use App\Models\User;
use App\Queries\PublishedArticlesQuery;
use Illuminate\Contracts\View\View;

/**
 * The author page is an E-E-A-T surface: Google reads it to decide whether the
 * bylines on this site belong to people who know the subject. It therefore
 * carries real structure — what they cover, how much, and since when — not just
 * a list of links.
 */
class AuthorController
{
    public function __invoke(string $locale, User $author): View
    {
        $articles = PublishedArticlesQuery::make()
            ->forLocale($locale)
            ->builder()
            ->where('author_id', $author->getKey())
            ->latest('published_at')
            ->paginate((int) setting('editorial.articles_per_page'));

        return view('pages.author', [
            'author' => $author,
            'articles' => $articles,
            'topics' => Topic::query()
                ->whereHas('articles', fn ($query) => $query
                    ->published()
                    ->where('author_id', $author->getKey()))
                ->with('translations')
                ->orderByDesc('articles_count')
                ->take(10)
                ->get(),
            'totalPublished' => PublishedArticlesQuery::make()
                ->forLocale($locale)->builder()->where('author_id', $author->getKey())->count(),
            'firstPublished' => PublishedArticlesQuery::make()
                ->forLocale($locale)->builder()->where('author_id', $author->getKey())
                ->min('published_at'),
        ]);
    }
}
