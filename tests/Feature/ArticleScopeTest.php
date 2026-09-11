<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;

it('includes an article published in the past', function (): void {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
    ]);

    expect(Article::published()->pluck('id'))->toContain($article->id);
});

it('excludes drafts', function (): void {
    $draft = Article::factory()->draft()->create();

    expect(Article::published()->pluck('id'))->not->toContain($draft->id);
});

it('excludes an article whose published_at is in the future', function (): void {
    // The dangerous case: status says published, the clock says not yet.
    $future = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->addDay(),
    ]);

    expect(Article::published()->pluck('id'))->not->toContain($future->id);
});

it('excludes an article with published status but no published_at', function (): void {
    $orphan = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => null,
    ]);

    expect(Article::published()->pluck('id'))->not->toContain($orphan->id);
});

it('excludes scheduled articles', function (): void {
    $scheduled = Article::factory()->scheduled()->create();

    expect(Article::published()->pluck('id'))->not->toContain($scheduled->id);
});

it('excludes soft deleted articles', function (): void {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
    ]);

    $article->delete();

    expect(Article::published()->pluck('id'))->not->toContain($article->id);
});

it('filters by locale', function (): void {
    $arabic = Article::factory()->create(['locale' => 'ar']);
    $english = Article::factory()->create(['locale' => 'en']);

    $ids = Article::forLocale('ar')->pluck('id');

    expect($ids)->toContain($arabic->id)
        ->and($ids)->not->toContain($english->id);
});

it('composes published and locale scopes', function (): void {
    $target = Article::factory()->create([
        'locale' => 'ar',
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
    ]);

    Article::factory()->create([
        'locale' => 'en',
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
    ]);

    expect(Article::published()->forLocale('ar')->pluck('id')->all())->toBe([$target->id]);
});
