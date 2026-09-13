<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Topic;
use App\Queries\PublishedArticlesQuery;
use Illuminate\Contracts\View\View;

/**
 * The newsletter surface.
 *
 * There is no newsletter-issue content type yet, so the "archive" is built from
 * featured topics — the same records an issue page renders. When issues become
 * real records this reads from them instead; nothing else changes.
 */
class NewsletterController
{
    public function __invoke(string $locale): View
    {
        return view('pages.newsletter', [
            'recent' => PublishedArticlesQuery::make()
                ->forLocale($locale)->latest()->limit(4)->get(),
            'issues' => Topic::query()
                ->where('is_featured', true)
                ->where('articles_count', '>', 0)
                ->with('translations')
                ->orderByDesc('articles_count')
                ->take(6)
                ->get(),
        ]);
    }
}
