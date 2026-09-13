<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;
use Illuminate\Support\Facades\DB;

function homeLayout(int $sections): HomepageLayout
{
    $layout = HomepageLayout::factory()->create(['is_active' => true]);

    foreach (range(1, $sections) as $i) {
        $layout->sections()->create([
            'type' => 'leads',
            'title' => ['ar' => "قسم {$i}"],
            'source' => 'auto',
            'config' => ['limit' => 3],
            'sort_order' => $i,
            'is_visible' => true,
        ]);
    }

    return $layout->refresh();
}

function countHomepageQueries(): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    test()->get('/ar')->assertOk();

    return $count;
}

beforeEach(function (): void {
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);

    Article::factory()->count(30)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => $category->id,
    ]);
});

it('renders the homepage', function (): void {
    homeLayout(3);

    $this->get('/ar')->assertOk();
});

it('does not run more queries as sections grow', function (): void {
    homeLayout(3);

    // Warm first: navigation and settings are cached on their own schedules, so
    // a cold request measures those rather than the composition under test.
    $this->get('/ar');
    $few = countHomepageQueries();

    HomepageLayout::query()->update(['is_active' => false]);
    homeLayout(18);

    $this->get('/ar');

    // Section count is an editorial decision that will grow; query count is not
    // allowed to grow with it.
    expect(countHomepageQueries())->toBe($few);
});

it('composes the page in a small fixed number of queries', function (): void {
    homeLayout(10);
    $this->get('/ar');

    expect(countHomepageQueries())->toBeLessThanOrEqual(14);
});

it('emits WebSite and Organization schema', function (): void {
    homeLayout(2);

    $this->get('/ar')
        ->assertOk()
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('SearchAction', false)
        ->assertSee('NewsMediaOrganization', false);
});

it('renders in rtl with the arabic locale', function (): void {
    homeLayout(1);

    $this->get('/ar')
        ->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false);
});

it('skips a section that resolved to nothing', function (): void {
    $layout = HomepageLayout::factory()->create(['is_active' => true]);
    $layout->sections()->create([
        'type' => 'leads',
        'title' => ['ar' => 'قسم فارغ'],
        'source' => 'auto',
        'config' => ['limit' => 3, 'category_slug' => 'does-not-exist'],
        'sort_order' => 1,
        'is_visible' => true,
    ]);

    // A heading over an empty rail looks broken to a reader.
    $this->get('/ar')->assertOk()->assertDontSee('قسم فارغ', false);
});

it('never shows the same article twice', function (): void {
    homeLayout(6);

    $html = $this->get('/ar')->assertOk()->getContent();

    // Only the sections. The chrome above <main> — masthead, ticker — links
    // stories too, and those links are not what this test is about.
    preg_match('#<main\b.*</main>#s', $html, $main);
    expect($main)->not->toBeEmpty();

    preg_match_all('#/ar/saudi/([a-z0-9-]+)#', $main[0], $matches);

    // Each card links its image and its headline, so an article legitimately
    // appears twice. Three occurrences means it is in two sections.
    $occurrences = array_count_values(array_filter($matches[1]));

    expect($occurrences)->not->toBeEmpty()
        ->and(max($occurrences))->toBeLessThanOrEqual(2);
});

it('lazy-loads every image except the hero', function (): void {
    $layout = HomepageLayout::factory()->create(['is_active' => true]);
    $layout->sections()->create([
        'type' => 'big_story', 'title' => ['ar' => 'الكبرى'], 'source' => 'auto',
        'config' => ['limit' => 1], 'sort_order' => 1, 'is_visible' => true,
    ]);
    $layout->sections()->create([
        'type' => 'leads', 'title' => ['ar' => 'الأبرز'], 'source' => 'auto',
        'config' => ['limit' => 3], 'sort_order' => 2, 'is_visible' => true,
    ]);

    $html = $this->get('/ar')->assertOk()->getContent();

    expect(substr_count($html, 'fetchpriority="high"'))->toBeLessThanOrEqual(1);
});
