<?php

declare(strict_types=1);

use App\Models\Article;
use Illuminate\Support\Str;

it('lets two articles sharing a translation group find each other', function (): void {
    $group = (string) Str::uuid();

    $arabic = Article::factory()->create(['locale' => 'ar', 'translation_group_id' => $group]);
    $english = Article::factory()->create(['locale' => 'en', 'translation_group_id' => $group]);

    expect($arabic->translations()->pluck('id')->all())->toBe([$english->id])
        ->and($english->translations()->pluck('id')->all())->toBe([$arabic->id]);
});

it('excludes the article itself from its translations', function (): void {
    $article = Article::factory()->create();

    expect($article->translations()->pluck('id')->all())->toBe([]);
});

it('assigns a translation group automatically on create', function (): void {
    $article = Article::factory()->create(['translation_group_id' => null]);

    expect($article->translation_group_id)->not->toBeNull()
        ->and(Str::isUuid($article->translation_group_id))->toBeTrue();
});

it('keeps an explicitly provided translation group', function (): void {
    $group = (string) Str::uuid();

    $article = Article::factory()->create(['translation_group_id' => $group]);

    expect($article->translation_group_id)->toBe($group);
});

it('does not link articles from different groups', function (): void {
    $first = Article::factory()->create(['locale' => 'ar']);
    Article::factory()->create(['locale' => 'en']);

    expect($first->translations()->count())->toBe(0);
});

it('finds the translation for a specific locale', function (): void {
    $group = (string) Str::uuid();

    $arabic = Article::factory()->create(['locale' => 'ar', 'translation_group_id' => $group]);
    $english = Article::factory()->create(['locale' => 'en', 'translation_group_id' => $group]);

    expect($arabic->translations()->where('locale', 'en')->first()->id)->toBe($english->id);
});

it('allows an article to have no translation at all', function (): void {
    // An English version is often never written. That is a normal state, not an error.
    $article = Article::factory()->create(['locale' => 'ar']);

    expect($article->translations()->exists())->toBeFalse();
});
