<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Article;
use App\Models\Category;
use App\Models\Topic;
use App\Support\DemoMode;
use App\Support\EntityUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController
{
    public function __construct(
        private readonly EntityUrl $urls,
        private readonly DemoMode $demo,
    ) {}

    /*
     * A sitemap in demo mode would hand a crawler the full list of generated
     * articles while robots.txt tells it to stay out — a contradiction that
     * resolves in the crawler's favour often enough to matter. 404 is the
     * honest answer: there is no sitemap for a site that is not to be indexed.
     */
    private function hiddenInDemoMode(): void
    {
        abort_if($this->demo->isOn(), 404);
    }

    public function index(): Response
    {
        $this->hiddenInDemoMode();

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
        $this->hiddenInDemoMode();

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
     * Every page in every enabled edition, each with its hreflang alternates:
     * the same section or topic in the other editions, and for a story, its
     * published translations only (App\Support\Alternates' rule, applied in
     * bulk — one query for the whole map instead of one per story).
     *
     * @return array<int, array{loc: string, lastmod: string|null, alternates: array<string, string>}>
     */
    private function entries(): array
    {
        $locales = \App\Support\Locales::enabled();
        $categories = Category::query()->where('is_active', true)->get();
        $topics = Topic::query()->get();
        $entries = [];

        $structural = [fn (string $l): string => route('web.home', $l, true)];
        foreach ($categories as $category) {
            $structural[] = fn (string $l): string => $this->urls->for($category, $l, true);
        }
        foreach ($topics as $topic) {
            $structural[] = fn (string $l): string => $this->urls->for($topic, $l, true);
        }

        foreach ($structural as $page) {
            $alternates = collect($locales)->mapWithKeys(fn (string $l): array => [$l => $page($l)])->all();
            foreach ($alternates as $loc) {
                $entries[] = ['loc' => $loc, 'lastmod' => null, 'alternates' => $alternates];
            }
        }

        $groups = [];
        Article::query()->published()->whereIn('locale', $locales)->with('category')
            ->chunkById(500, function ($articles) use (&$groups): void {
                foreach ($articles as $article) {
                    $groups[$article->translation_group_id][$article->locale] = [
                        'loc' => $this->urls->for($article, $article->locale, true),
                        'lastmod' => ($article->updated_content_at ?? $article->published_at)?->toAtomString(),
                    ];
                }
            });

        foreach ($groups as $versions) {
            $alternates = array_map(fn (array $v): string => $v['loc'], $versions);
            foreach ($versions as $version) {
                $entries[] = [...$version, 'alternates' => count($alternates) > 1 ? $alternates : []];
            }
        }

        return array_values(array_filter($entries, fn (array $e): bool => $e['loc'] !== null));
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
