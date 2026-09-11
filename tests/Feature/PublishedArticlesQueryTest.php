<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Topic;
use App\Queries\PublishedArticlesQuery;
use Illuminate\Support\Facades\DB;

function livePublished(array $attributes = []): Article
{
    return Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        ...$attributes,
    ]);
}

/**
 * Count the queries a callback runs. This is the N+1 proof: the number must not
 * grow with the number of rows rendered.
 */
function countQueries(Closure $callback): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    $callback();

    return $count;
}

it('returns published articles', function (): void {
    $article = livePublished();

    expect(PublishedArticlesQuery::make()->get()->pluck('id'))->toContain($article->id);
});

it('excludes drafts', function (): void {
    $draft = Article::factory()->draft()->create();

    expect(PublishedArticlesQuery::make()->get()->pluck('id'))->not->toContain($draft->id);
});

it('excludes articles dated in the future', function (): void {
    $future = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->addDay(),
    ]);

    expect(PublishedArticlesQuery::make()->get()->pluck('id'))->not->toContain($future->id);
});

it('excludes soft deleted articles', function (): void {
    $article = livePublished();
    $article->delete();

    expect(PublishedArticlesQuery::make()->get()->pluck('id'))->not->toContain($article->id);
});

it('filters by locale', function (): void {
    $arabic = livePublished(['locale' => 'ar']);
    $english = livePublished(['locale' => 'en']);

    $ids = PublishedArticlesQuery::make()->forLocale('ar')->get()->pluck('id');

    expect($ids)->toContain($arabic->id)->and($ids)->not->toContain($english->id);
});

it('filters by category', function (): void {
    $category = Category::factory()->create();
    $inside = livePublished(['category_id' => $category->id]);
    $outside = livePublished();

    $ids = PublishedArticlesQuery::make()->inCategory($category)->get()->pluck('id');

    expect($ids)->toContain($inside->id)->and($ids)->not->toContain($outside->id);
});

it('filters by topic', function (): void {
    $topic = Topic::factory()->create();
    $tagged = livePublished();
    $tagged->topics()->attach($topic->id);
    $untagged = livePublished();

    $ids = PublishedArticlesQuery::make()->withTopic($topic)->get()->pluck('id');

    expect($ids)->toContain($tagged->id)->and($ids)->not->toContain($untagged->id);
});

it('filters by mentioned entity', function (): void {
    $company = Company::factory()->create();
    $mentioning = livePublished();
    $mentioning->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
    ]);
    $other = livePublished();

    $ids = PublishedArticlesQuery::make()->mentioning($company)->get()->pluck('id');

    expect($ids)->toContain($mentioning->id)->and($ids)->not->toContain($other->id);
});

it('filters featured articles', function (): void {
    $featured = livePublished(['is_featured' => true]);
    $plain = livePublished(['is_featured' => false]);

    $ids = PublishedArticlesQuery::make()->featured()->get()->pluck('id');

    expect($ids)->toContain($featured->id)->and($ids)->not->toContain($plain->id);
});

it('excludes given ids', function (): void {
    $keep = livePublished();
    $drop = livePublished();

    $ids = PublishedArticlesQuery::make()->excluding([$drop->id])->get()->pluck('id');

    expect($ids)->toContain($keep->id)->and($ids)->not->toContain($drop->id);
});

it('orders newest first', function (): void {
    $older = livePublished(['published_at' => now()->subWeek()]);
    $newer = livePublished(['published_at' => now()->subHour()]);

    expect(PublishedArticlesQuery::make()->latest()->get()->pluck('id')->take(2)->all())
        ->toBe([$newer->id, $older->id]);
});

it('paginates', function (): void {
    Article::factory()->count(5)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
    ]);

    expect(PublishedArticlesQuery::make()->latest()->paginate(2))->toHaveCount(2);
});

it('runs a constant number of queries regardless of row count', function (): void {
    $category = Category::factory()->create();

    Article::factory()->count(3)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'category_id' => $category->id,
    ]);

    $render = function (): void {
        foreach (PublishedArticlesQuery::make()->latest()->get() as $article) {
            // Exactly what a listing template touches.
            $article->title;
            $article->category->name;
            $article->author->name;
            $article->heroMedia;
        }
    };

    $few = countQueries($render);

    Article::factory()->count(12)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'category_id' => $category->id,
    ]);

    $many = countQueries($render);

    expect($many)->toBe($few)
        ->and($few)->toBeLessThanOrEqual(6);
});

it('never loads blocks, sources or revisions', function (): void {
    $article = livePublished();
    $article->blocks()->create(['type' => 'paragraph', 'data' => ['text' => 'نص'], 'sort_order' => 0]);

    $loaded = PublishedArticlesQuery::make()->get()->first();

    expect($loaded->relationLoaded('blocks'))->toBeFalse()
        ->and($loaded->relationLoaded('sources'))->toBeFalse()
        ->and($loaded->relationLoaded('revisions'))->toBeFalse();
});
