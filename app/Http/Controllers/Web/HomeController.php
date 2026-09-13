<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;
use App\Models\Opportunity;
use App\Queries\ComposeHomepage;
use App\Support\MarketFigures;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The front page. All resolution lives in ComposeHomepage — the same object the
 * admin composer previews through, so what an editor sees is what ships.
 *
 * The page also needs a handful of things no section owns: the ticker, the
 * indices the markets band names, the regions the intelligence band lists, and
 * the counts under the issue cover. They are fetched once and cached with the
 * composition, so the section count is still constant and the query count is
 * still bounded.
 */
class HomeController
{
    public function __invoke(string $locale, ComposeHomepage $compose): View
    {
        $layout = HomepageLayout::query()->active()->orderByDesc('id')->first()
            ?? HomepageLayout::query()->where('is_active', true)->first();

        $sections = $layout === null
            ? collect()
            : Cache::tags(['homepage', 'articles', "locale:{$locale}"])->remember(
                "homepage:{$layout->getKey()}:{$locale}",
                (int) config('masar.cache.homepage'),
                fn () => $compose($layout, $locale),
            );

        $chrome = Cache::tags(['homepage', 'articles', "locale:{$locale}"])->remember(
            "homepage:chrome:{$locale}",
            (int) config('masar.cache.homepage'),
            fn (): array => [
                'opportunityOfTheWeek' => Opportunity::query()
                    ->with('industry')
                    ->orderByDesc('published_at')
                    ->first(),

                // The tiles row is the site's map, so it takes the real
                // categories. Each borrows the newest hero in it — a category
                // has no media of its own.
                'categoryTiles' => Category::query()
                    ->with('translations')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->limit(6)
                    ->get()
                    ->map(fn (Category $category): array => [
                        'category' => $category,
                        'media' => Article::query()
                            ->published()
                            ->where('category_id', $category->getKey())
                            ->whereNotNull('hero_media_id')
                            ->latest('published_at')
                            ->with('heroMedia')
                            ->first()?->heroMedia,
                    ]),

                // Opportunities have no media of their own; the cards borrow
                // distinct heroes so none repeats inside the row.
                'opportunityMedia' => Article::query()
                    ->published()
                    ->whereNotNull('hero_media_id')
                    ->inRandomOrder()
                    ->limit(7)
                    ->with('heroMedia')
                    ->get()
                    ->map->heroMedia
                    ->filter()
                    ->values(),

                'topbarLinks' => [
                    ['label' => 'من نحن', 'url' => route('web.about', $locale)],
                    ['label' => 'معايير التحرير', 'url' => route('web.editorial-standards', $locale)],
                    ['label' => 'تواصل', 'url' => route('web.contact', $locale)],
                ],
            ],
        );

        return view('pages.home', [
            'layout' => $layout,
            'sections' => $sections,
            // Every figure the page renders. No feed: an editor types them in
            // and they carry a date and a source, or they do not render.
            'figures' => app(MarketFigures::class),
            'issueStats' => $this->issueStats($sections),
            ...$chrome,
        ]);
    }

    /**
     * The four counts under the issue cover, counted from the issue itself.
     *
     * @param  Collection<int, array<string, mixed>>  $sections
     * @return array<string, int>
     */
    private function issueStats(Collection $sections): array
    {
        /** @var Collection<int, Article> $items */
        $items = collect($sections->firstWhere('type', 'issue')['items'] ?? [])
            ->filter(fn ($item): bool => $item instanceof Article);

        if ($items->isEmpty()) {
            return [];
        }

        return [
            'مواد' => $items->count(),
            'أقسام' => $items->pluck('category_id')->filter()->unique()->count(),
            'كتّاب' => $items->pluck('author_id')->filter()->unique()->count(),
            'دقائق قراءة' => (int) $items->sum('reading_time'),
        ];
    }
}
