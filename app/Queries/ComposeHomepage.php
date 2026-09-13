<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\HomepageSectionType;
use App\Models\Article;
use App\Models\Company;
use App\Models\HomepageLayout;
use App\Models\HomepageSection;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Resolves a homepage layout into the content it actually renders.
 *
 * The admin composer and the public front page both consume this, which is the
 * entire point: a preview that resolves content differently from the live page
 * is worse than no preview, because it is confidently wrong.
 *
 * Two rules shape the implementation:
 *
 *  1. **No article appears twice on the page**, and an explicit pin always beats
 *     a query. Resolution is two-pass: every manually pinned article is claimed
 *     first, then automatic sections fill from what is left. A single pass in
 *     document order would let an auto section near the top swallow a story that
 *     an editor had deliberately pinned to a section further down — the pin is
 *     the higher-intent choice and must win regardless of position.
 *
 *  2. **Content is fetched in bulk, not per section.** Section count is an
 *     editorial decision that will grow; query count must not grow with it.
 */
class ComposeHomepage
{
    /**
     * Ids already placed on the page, in placement order.
     *
     * @var array<int, true>
     */
    private array $usedArticleIds = [];

    /** @var array<int, true> */
    private array $usedOpportunityIds = [];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function __invoke(HomepageLayout $layout, string $locale): Collection
    {
        $this->usedArticleIds = [];
        $this->usedOpportunityIds = [];

        $sections = $layout->sections()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();

        // Everything the page could need, in a fixed number of queries.
        $pool = $this->buildPool($sections, $locale);

        // Pass one: reserve every pinned article for the section that pinned it.
        $reserved = $this->claimPinned($sections, $pool);

        // Pass two: resolve in document order, auto sections filling around them.
        return $sections
            ->map(fn (HomepageSection $section): array => $this->resolve(
                $section,
                $pool,
                $locale,
                $reserved[$section->getKey()] ?? collect(),
            ))
            ->values();
    }

    /**
     * Preload the candidate content once.
     *
     * `manual` sections name their ids up front, so those are fetched in a
     * single whereIn. Automatic sections draw from one generous pool of recent
     * published articles, filtered in memory — which is correct here because the
     * pool is bounded by the page size, not the archive.
     *
     * @param  EloquentCollection<int, HomepageSection>  $sections
     * @return array{articles: Collection<int, Article>, manual: Collection<int, Article>, opportunities: Collection<int, Opportunity>, companies: Collection<int, Company>}
     */
    private function buildPool(EloquentCollection $sections, string $locale): array
    {
        $manualIds = $sections
            ->flatMap(fn (HomepageSection $s): array => (array) ($s->config['article_ids'] ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $manual = $manualIds->isEmpty()
            ? collect()
            : PublishedArticlesQuery::make()
                ->forLocale($locale)
                ->builder()
                ->whereIn('id', $manualIds->all())
                ->with('topics:id')
                ->get()
                ->keyBy('id');

        // One pool for every auto/mixed section. Sized from the layout's own
        // appetite so a page of many large sections still fills.
        $appetite = (int) $sections->sum(
            fn (HomepageSection $s): int => (int) ($s->config['limit'] ?? $this->typeOf($s)->defaultLimit()),
        );

        $articles = PublishedArticlesQuery::make()
            ->forLocale($locale)
            ->latest()
            ->limit(max(30, $appetite * 3))
            // Topic filtering reads $article->topics; without this the section
            // rules would fire one query per candidate article.
            ->builder()
            ->with('topics:id')
            ->get();

        $needsOpportunities = $sections->contains(
            fn (HomepageSection $s): bool => $this->typeOf($s)->isOpportunities(),
        );

        $opportunities = $needsOpportunities
            ? Opportunity::query()
                ->published()
                ->open()
                ->forLocale($locale)
                ->with(['industry.translations', 'country.translations'])
                ->orderByDesc('published_at')
                ->limit(30)
                ->get()
            : collect();

        $needsCompanies = $sections->contains(
            fn (HomepageSection $s): bool => $this->typeOf($s)->isCompanies(),
        );

        // Most-referenced companies: the graph already ranks them, so the rail
        // is a read of mentions_count rather than an aggregate at render time.
        $companies = $needsCompanies
            ? Company::query()
                ->with('translations')
                ->where('mentions_count', '>', 0)
                ->orderByDesc('mentions_count')
                ->limit(20)
                ->get()
            : collect();

        return [
            'articles' => $articles,
            'manual' => $manual,
            'opportunities' => $opportunities,
            'companies' => $companies,
        ];
    }

    /**
     * Claim pinned articles up front, in section order, so a later manual
     * section cannot lose its picks to an earlier automatic one.
     *
     * @param  EloquentCollection<int, HomepageSection>  $sections
     * @param  array<string, Collection<int, mixed>>  $pool
     * @return array<int, Collection<int, Article>>
     */
    private function claimPinned(EloquentCollection $sections, array $pool): array
    {
        $claimed = [];

        foreach ($sections as $section) {
            if (! in_array($section->source, ['manual', 'mixed'], true)) {
                continue;
            }

            $type = $this->typeOf($section);

            if ($type->isStatic() || $type->isOpportunities() || $type->isCompanies()) {
                continue;
            }

            $limit = (int) ($section->config['limit'] ?? $type->defaultLimit());
            $picks = collect();

            foreach ((array) ($section->config['article_ids'] ?? []) as $id) {
                if ($picks->count() >= $limit) {
                    break;
                }

                $article = $pool['manual']->get((int) $id);

                if ($article === null || isset($this->usedArticleIds[$article->id])) {
                    continue;
                }

                $picks->push($article);
                $this->usedArticleIds[$article->id] = true;
            }

            $claimed[$section->getKey()] = $picks;
        }

        return $claimed;
    }

    /**
     * @param  array{articles: Collection<int, Article>, manual: Collection<int, Article>, opportunities: Collection<int, Opportunity>, companies: Collection<int, Company>}  $pool
     * @param  Collection<int, Article>  $pinned
     * @return array<string, mixed>
     */
    private function resolve(HomepageSection $section, array $pool, string $locale, Collection $pinned): array
    {
        $type = $this->typeOf($section);
        $limit = (int) ($section->config['limit'] ?? $type->defaultLimit());

        $items = match (true) {
            $type->isStatic() => collect(),
            $type->isOpportunities() => $this->resolveOpportunities($pool['opportunities'], $limit),
            $type->isCompanies() => $pool['companies']->take($limit)->values(),
            default => $this->resolveArticles($section, $type, $pool, $limit, $pinned),
        };

        return [
            'id' => $section->getKey(),
            'type' => $type->value,
            'title' => $section->title($locale) ?? $type->label(),
            'source' => $section->source,
            'limit' => $limit,
            'items' => $items,
        ];
    }

    /**
     * @param  array{articles: Collection<int, Article>, manual: Collection<int, Article>, opportunities: Collection<int, Opportunity>, companies: Collection<int, Company>}  $pool
     * @return Collection<int, Article>
     */
    private function resolveArticles(
        HomepageSection $section,
        HomepageSectionType $type,
        array $pool,
        int $limit,
        Collection $pinned,
    ): Collection {
        // Pinned items were already claimed in pass one, in the editor's order.
        $picked = $pinned->take($limit)->values();

        // `manual` stops here even if short — an editor who picked three stories
        // wants three, not three plus whatever the query found.
        if ($section->source === 'manual') {
            return $picked;
        }

        foreach ($this->candidates($pool['articles'], $section, $type) as $article) {
            if ($picked->count() >= $limit) {
                break;
            }

            if (isset($this->usedArticleIds[$article->id])) {
                continue;
            }

            $picked->push($article);
            $this->usedArticleIds[$article->id] = true;
        }

        return $picked;
    }

    /**
     * The rule that fills an automatic section.
     *
     * @param  Collection<int, Article>  $articles
     * @return Collection<int, Article>
     */
    private function candidates(Collection $articles, HomepageSection $section, HomepageSectionType $type): Collection
    {
        $config = $section->config ?? [];

        $filtered = $articles;

        if (filled($config['category_slug'] ?? null)) {
            $filtered = $filtered->filter(
                fn (Article $a): bool => $a->category?->slug === $config['category_slug'],
            );
        }

        if (filled($config['content_type'] ?? null)) {
            $filtered = $filtered->filter(
                fn (Article $a): bool => $a->content_type->value === $config['content_type'],
            );
        }

        if (filled($config['topic_id'] ?? null)) {
            $topicId = (int) $config['topic_id'];
            $filtered = $filtered->filter(
                fn (Article $a): bool => $a->topics->contains('id', $topicId),
            );
        }

        if ($config['only_featured'] ?? false) {
            $filtered = $filtered->filter(fn (Article $a): bool => (bool) $a->is_featured);
        }

        return match ($type) {
            HomepageSectionType::MostRead => $filtered->sortByDesc('views_count')->values(),
            HomepageSectionType::EditorsPicks => $filtered->filter(fn (Article $a): bool => (bool) $a->is_featured)->values(),
            default => $filtered->values(),
        };
    }

    /**
     * @param  Collection<int, Opportunity>  $opportunities
     * @return Collection<int, Opportunity>
     */
    private function resolveOpportunities(Collection $opportunities, int $limit): Collection
    {
        $picked = collect();

        foreach ($opportunities as $opportunity) {
            if ($picked->count() >= $limit) {
                break;
            }

            if (isset($this->usedOpportunityIds[$opportunity->id])) {
                continue;
            }

            $picked->push($opportunity);
            $this->usedOpportunityIds[$opportunity->id] = true;
        }

        return $picked;
    }

    private function typeOf(HomepageSection $section): HomepageSectionType
    {
        return HomepageSectionType::tryFrom((string) $section->type) ?? HomepageSectionType::Leads;
    }
}
