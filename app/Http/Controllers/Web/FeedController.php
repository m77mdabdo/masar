<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Queries\PublishedArticlesQuery;
use App\Support\EntityUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class FeedController
{
    public function __construct(
        private readonly EntityUrl $urls,
    ) {}

    /**
     * The unprefixed feed sends readers to the default locale's feed rather than
     * guessing — a mixed-language RSS feed is useful to nobody.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('web.rss.locale', config('masar.default_locale'), 302);
    }

    public function locale(string $locale): Response
    {
        $articles = PublishedArticlesQuery::make()
            ->forLocale($locale)
            ->latest()
            ->limit(50)
            ->get();

        return response(
            view('feeds.rss', [
                'articles' => $articles,
                'urls' => $this->urls,
                'feedLocale' => $locale,
            ])->render(),
            200,
            ['Content-Type' => 'application/rss+xml; charset=UTF-8'],
        );
    }
}
