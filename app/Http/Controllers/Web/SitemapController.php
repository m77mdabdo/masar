<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Article;
use App\Models\Category;
use App\Models\Topic;
use App\Support\EntityUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController
{
    public function __construct(
        private readonly EntityUrl $urls,
    ) {}

    public function index(): Response
    {
        $entries = Cache::tags(['articles', 'navigation'])->remember(
            'sitemap',
            (int) config('masar.cache.lists'),
            fn (): array => $this->entries(),
        );

        return $this->xml(view('feeds.sitemap', ['entries' => $entries])->render());
    }

    /**
     * Google News only considers the last 48 hours, so anything older is noise
     * that dilutes the feed.
     */
    public function news(): Response
    {
        $articles = Article::query()
            ->published()
            ->where('published_at', '>=', now()->subHours(48))
            ->with('category')
            ->orderByDesc('published_at')
            ->limit(1000)
            ->get();

        return $this->xml(view('feeds.news-sitemap', [
            'articles' => $articles,
            'urls' => $this->urls,
        ])->render());
    }

    /**
     * @return array<int, array{loc: string, lastmod: string|null}>
     */
    private function entries(): array
    {
        $entries = [];

        foreach (array_keys((array) config('masar.locales')) as $locale) {
            if (! config("masar.locales.{$locale}.enabled")) {
                continue;
            }

            $entries[] = ['loc' => route('web.home', $locale, true), 'lastmod' => null];

            foreach (Category::query()->where('is_active', true)->get() as $category) {
                $entries[] = ['loc' => $this->urls->for($category, $locale, true), 'lastmod' => null];
            }

            foreach (Topic::query()->get() as $topic) {
                $entries[] = ['loc' => $this->urls->for($topic, $locale, true), 'lastmod' => null];
            }

            Article::query()
                ->published()
                ->where('locale', $locale)
                ->with('category')
                ->chunkById(500, function ($articles) use (&$entries, $locale): void {
                    foreach ($articles as $article) {
                        $entries[] = [
                            'loc' => $this->urls->for($article, $locale, true),
                            'lastmod' => ($article->updated_content_at ?? $article->published_at)?->toAtomString(),
                        ];
                    }
                });
        }

        return array_values(array_filter($entries, fn (array $e): bool => $e['loc'] !== null));
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
