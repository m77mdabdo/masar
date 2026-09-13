<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;
use App\Queries\ComposeHomepage;
use Illuminate\Support\Facades\DB;

/**
 * The front page is the most-requested page on the site. Its query count must be
 * a function of the *kinds* of content it shows, never of how many sections an
 * editor happens to have added.
 */
function countComposeQueries(HomepageLayout $layout): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    $page = app(ComposeHomepage::class)($layout, 'ar');

    // Touch what a template touches, so lazy loading would show up here.
    foreach ($page as $section) {
        foreach ($section['items'] as $item) {
            $item->title;

            if ($item instanceof Article) {
                $item->category?->name;
                $item->author?->name;
            }
        }
    }

    return $count;
}

function layoutOfSize(int $sections): HomepageLayout
{
    $layout = HomepageLayout::factory()->create(['is_active' => false]);

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

beforeEach(function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);

    Article::factory()->count(40)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => $category->id,
    ]);
});

it('does not run more queries as sections grow', function (): void {
    $small = countComposeQueries(layoutOfSize(3));
    $large = countComposeQueries(layoutOfSize(20));

    expect($large)->toBe($small);
});

it('composes the page in a small fixed number of queries', function (): void {
    expect(countComposeQueries(layoutOfSize(12)))->toBeLessThanOrEqual(8);
});

it('does not run a query per rendered article', function (): void {
    $few = countComposeQueries(layoutOfSize(2));

    Article::factory()->count(40)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
    ]);

    expect(countComposeQueries(layoutOfSize(2)))->toBe($few);
});

it('eager loads everything a listing card renders', function (): void {
    $page = app(ComposeHomepage::class)(layoutOfSize(1), 'ar');
    $article = $page[0]['items']->first();

    expect($article->relationLoaded('category'))->toBeTrue()
        ->and($article->relationLoaded('author'))->toBeTrue()
        // Topic rules read this; without it each candidate would cost a query.
        ->and($article->relationLoaded('topics'))->toBeTrue();
});

it('never loads blocks or revisions onto the front page', function (): void {
    $page = app(ComposeHomepage::class)(layoutOfSize(1), 'ar');
    $article = $page[0]['items']->first();

    expect($article->relationLoaded('blocks'))->toBeFalse()
        ->and($article->relationLoaded('revisions'))->toBeFalse();
});

it('stays flat when opportunity sections are added', function (): void {
    $layout = layoutOfSize(3);

    $before = countComposeQueries($layout);

    foreach (range(1, 3) as $i) {
        $layout->sections()->create([
            'type' => 'opportunities',
            'title' => ['ar' => 'فرص'],
            'source' => 'auto',
            'config' => ['limit' => 3],
            'sort_order' => 100 + $i,
            'is_visible' => true,
        ]);
    }

    // One extra query for the opportunity pool, regardless of how many
    // opportunity sections exist.
    expect(countComposeQueries($layout->refresh()))->toBeLessThanOrEqual($before + 3);
});
