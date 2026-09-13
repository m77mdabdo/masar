<?php

declare(strict_types=1);

use App\Actions\Redirects\ValidateRedirect;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Topic;
use App\Queries\MenuTreeQuery;
use App\Support\EntityUrl;

/**
 * The URL contract.
 *
 * Every public URL is defined once, by a named route. These tests exist because
 * before EntityUrl there were three separate implementations of "what is the URL
 * for this thing" — the header, redirect validation and the templates — and they
 * were free to disagree without anything failing.
 */
it('resolves every named public route', function (string $name, array $params): void {
    expect(route($name, $params))->toBeString()->not->toBeEmpty();
})->with([
    ['web.home', ['ar']],
    ['web.search', ['ar']],
    ['web.markets', ['ar']],
    ['web.video.index', ['ar']],
    ['web.newsletter', ['ar']],
    ['web.about', ['ar']],
    ['web.contact', ['ar']],
    ['web.editorial-standards', ['ar']],
    ['web.opportunities.index', ['ar']],
    ['web.opportunity.show', ['ar', 'a-slug']],
    ['web.topic.show', ['ar', 'a-slug']],
    ['web.company.show', ['ar', 'a-slug']],
    ['web.person.show', ['ar', 'a-slug']],
    ['web.author.show', ['ar', 1]],
    ['web.issue.show', ['ar', 'a-slug']],
    ['web.category.show', ['ar', 'saudi']],
    ['web.article.show', ['ar', 'saudi', 'a-slug']],
    ['web.rss.locale', ['ar']],
    ['web.robots', []],
    ['web.sitemap', []],
    ['web.sitemap.news', []],
    ['web.root', []],
]);

it('builds a locale-prefixed url for every entity type', function (): void {
    $urls = app(EntityUrl::class);
    $category = Category::factory()->create(['slug' => 'saudi']);

    $article = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'slug' => 'a-story',
        'category_id' => $category->id,
    ]);

    expect($urls->for($article, 'ar'))->toBe('/ar/saudi/a-story')
        ->and($urls->for($category, 'ar'))->toBe('/ar/saudi')
        ->and($urls->for(Topic::factory()->create(['slug' => 't']), 'ar'))->toBe('/ar/topics/t')
        ->and($urls->for(Company::factory()->create(['slug' => 'c']), 'ar'))->toBe('/ar/companies/c')
        ->and($urls->for(Person::factory()->create(['slug' => 'p']), 'ar'))->toBe('/ar/people/p')
        ->and($urls->for(Opportunity::factory()->create(['slug' => 'o', 'locale' => 'ar']), 'ar'))->toBe('/ar/opportunities/o');
});

it('produces the same url through the menu as through EntityUrl', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);
    $menu = Menu::factory()->create(['key' => 'header-'.uniqid()]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'السعودية'],
        'url' => null,
        'linkable_type' => $category->getMorphClass(),
        'linkable_id' => $category->getKey(),
    ]);

    $fromMenu = app(MenuTreeQuery::class)($menu->key, 'ar')->first()['url'];
    $fromHelper = app(EntityUrl::class)->for($category, 'ar');

    // The header and the canonical tag must never disagree about a URL.
    expect($fromMenu)->toBe($fromHelper);
});

it('validates redirects against the same url the menu builds', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);
    $url = app(EntityUrl::class)->for($category, 'ar');

    $problems = app(ValidateRedirect::class)($url, '/ar/elsewhere');

    // Shadowing a live URL is silent content loss, so the two must agree on
    // exactly which strings are live.
    expect($problems)->not->toBeEmpty();
});

it('agrees with the menu for every entity type when validating redirects', function (): void {
    $urls = app(EntityUrl::class);

    $entities = [
        Category::factory()->create(['slug' => 'cat', 'is_active' => true]),
        Topic::factory()->create(['slug' => 'top']),
        Company::factory()->create(['slug' => 'co']),
        Person::factory()->create(['slug' => 'per']),
    ];

    foreach ($entities as $entity) {
        $url = $urls->for($entity, 'ar');

        expect(app(ValidateRedirect::class)->shadowsLiveContent($url))
            ->toBeTrue(class_basename($entity).' url should register as live');
    }
});

it('does not treat an unknown path as live content', function (): void {
    expect(app(ValidateRedirect::class)->shadowsLiveContent('/ar/2019/an-old-url'))->toBeFalse();
});

it('keeps fixed sections from being swallowed by the category catch-all', function (): void {
    // `/{locale}/{category}` would otherwise match `/ar/topics/x` and every
    // top-level file route.
    expect(route('web.topic.show', ['ar', 'x'], absolute: false))->toBe('/ar/topics/x')
        ->and(route('web.sitemap', absolute: false))->toBe('/sitemap.xml')
        ->and(route('web.rss.locale', 'ar', absolute: false))->toBe('/ar/rss.xml');
});

it('builds absolute urls for feeds and schema', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);

    expect(app(EntityUrl::class)->for($category, 'ar', true))->toStartWith('http');
});
