<?php

declare(strict_types=1);

use App\Enums\TranslationStatus;
use App\Models\Article;
use App\Models\HomepageLayout;
use App\Models\Opportunity;
use App\Queries\ComposeHomepage;
use App\Queries\PublishedArticlesQuery;

/*
 * Every edition's front page has the same structure as the Arabic one.
 *
 * Until 2026-10-29 the big story — the cinematic hero — rendered in Arabic
 * only. Its section is `manual`, pinned to three story ids, and those ids are
 * the Arabic rows: fetching them "within the edition" matched nothing in the
 * other five, the section resolved empty and the hero was not drawn. Translation
 * tests passed throughout, because every string that did render was translated.
 * These tests hold the structure, not the strings.
 */

const EDITIONS = ['ar', 'en', 'fr', 'hi', 'ur-pk', 'zh-hans'];

/** An Arabic story and a published translation in each other edition. */
function storyInEveryEdition(array $attributes = []): Article
{
    $source = publishableArticle(['status' => \App\Enums\ArticleStatus::Published, 'published_at' => now()->subDay(), 'locale' => 'ar', ...$attributes]);
    foreach (array_slice(EDITIONS, 1) as $locale) {
        Article::factory()->published()->create([
            'locale' => $locale,
            'translation_group_id' => $source->translation_group_id,
            'source_article_id' => $source->id,
            'translation_status' => TranslationStatus::OwnerAuthorized,
            'category_id' => $source->category_id,
            'hero_media_id' => $source->hero_media_id,
            'hero_alt' => 'Alt '.$locale,
            'published_at' => $source->published_at,
            'slug' => $source->slug,
        ]);
    }

    return $source;
}

/** The front page as the seeder builds it: a pinned big story first. */
function layoutPinning(array $ids): HomepageLayout
{
    $layout = HomepageLayout::factory()->create(['is_active' => true]);
    $layout->sections()->create(['type' => 'big_story', 'title' => ['ar' => 'القصة الكبرى'], 'source' => 'manual',
        'config' => ['limit' => 3, 'article_ids' => $ids], 'sort_order' => 1, 'is_visible' => true]);
    $layout->sections()->create(['type' => 'leads', 'title' => ['ar' => 'أبرز'], 'source' => 'auto',
        'config' => ['limit' => 3], 'sort_order' => 2, 'is_visible' => true]);

    return $layout->refresh();
}

beforeEach(function (): void {
    foreach (EDITIONS as $code) {
        config()->set("masar.locales.{$code}.enabled", true);
    }
});

it('resolves a pinned story to its own translation in every edition, in the editor’s order', function (): void {
    $stories = [storyInEveryEdition(), storyInEveryEdition(), storyInEveryEdition()];
    $layout = layoutPinning(array_map(fn (Article $a): int => $a->id, $stories));
    $groups = array_map(fn (Article $a): string => $a->translation_group_id, $stories);

    foreach (EDITIONS as $locale) {
        $hero = app(ComposeHomepage::class)($layout, $locale)->firstWhere('type', 'big_story');

        expect($hero['items']->pluck('translation_group_id')->all())->toBe($groups, "{$locale}: the pinned stories")
            ->and($hero['items']->pluck('locale')->unique()->all())->toBe([$locale], "{$locale}: rows of this edition only");
    }
});

it('leaves a pin empty where its translation is missing, and never shows another edition’s text', function (): void {
    $kept = storyInEveryEdition();
    $missing = storyInEveryEdition();
    Article::query()->where('translation_group_id', $missing->translation_group_id)->where('locale', 'fr')->delete();
    $layout = layoutPinning([$kept->id, $missing->id]);

    $hero = app(ComposeHomepage::class)($layout, 'fr')->firstWhere('type', 'big_story');

    expect($hero['items']->pluck('translation_group_id')->all())->toBe([$kept->translation_group_id])
        ->and($hero['items']->pluck('locale')->unique()->all())->toBe(['fr']);
});

it('draws the big story on every edition’s front page, with the same sections as the Arabic one', function (): void {
    $stories = [storyInEveryEdition(), storyInEveryEdition(), storyInEveryEdition()];
    layoutPinning(array_map(fn (Article $a): int => $a->id, $stories));

    $structure = [];
    foreach (EDITIONS as $locale) {
        $response = $this->get("/{$locale}")->assertOk();
        $html = $response->getContent();
        preg_match_all('/<section[^>]*aria-labelledby="([^"]+)"/', $html, $m);
        $structure[$locale] = $m[1];

        // One needle per call: toContain() reads every argument as a needle.
        expect($html)->toContain('id="big-story-heading"')->toContain('data-carousel');
    }

    foreach (array_slice(EDITIONS, 1) as $locale) {
        expect($structure[$locale])->toBe($structure['ar'], "{$locale}: same sections, same order");
    }
});

it('orders stories published at the same moment identically in every edition', function (): void {
    // The originals in one order, their translations created in the other:
    // a row's own id then breaks the tie differently per edition.
    $at = now()->subDays(2)->startOfDay();
    $first = publishableArticle(['status' => \App\Enums\ArticleStatus::Published, 'published_at' => $at, 'locale' => 'ar']);
    $second = publishableArticle(['status' => \App\Enums\ArticleStatus::Published, 'published_at' => $at, 'locale' => 'ar']);
    foreach ([$second, $first] as $source) {
        Article::factory()->published()->create(['locale' => 'en', 'translation_group_id' => $source->translation_group_id,
            'source_article_id' => $source->id, 'translation_status' => TranslationStatus::OwnerAuthorized,
            'category_id' => $source->category_id, 'published_at' => $at, 'slug' => $source->slug]);
    }

    $order = fn (string $locale): array => PublishedArticlesQuery::make()->forLocale($locale)->latest()->builder()->limit(2)->pluck('translation_group_id')->all();

    expect($order('en'))->toBe($order('ar'))
        ->and(Article::query()->where('locale', 'en')->newestFirst()->limit(2)->pluck('translation_group_id')->all())->toBe($order('ar'));
});

it('shows each edition its own published, open opportunity of the week', function (): void {
    storyInEveryEdition();
    layoutPinning([]);
    $arabic = Opportunity::factory()->create(['locale' => 'ar', 'published_at' => now()->subDays(3), 'deadline' => now()->addMonth(), 'status' => 'open']);
    // Newer, but another edition's — and, separately, a newer Arabic one that is closed.
    Opportunity::factory()->create(['locale' => 'en', 'published_at' => now()->subDay(), 'deadline' => now()->addMonth(), 'status' => 'open']);
    Opportunity::factory()->create(['locale' => 'ar', 'published_at' => now()->subHours(2), 'deadline' => now()->addMonth(), 'status' => 'closed']);

    $shown = $this->get('/ar')->assertOk()->viewData('opportunityOfTheWeek');

    expect($shown?->id)->toBe($arabic->id);
});
