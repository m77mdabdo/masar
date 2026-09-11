<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Opportunity;
use Illuminate\Database\UniqueConstraintViolationException;

it('allows the same slug in different locales', function (): void {
    $arabic = Article::factory()->create(['locale' => 'ar', 'slug' => 'foreign-property-ownership']);
    $english = Article::factory()->create(['locale' => 'en', 'slug' => 'foreign-property-ownership']);

    expect($arabic->slug)->toBe($english->slug)
        ->and($arabic->locale)->not->toBe($english->locale)
        ->and(Article::where('slug', 'foreign-property-ownership')->count())->toBe(2);
});

it('rejects a duplicate slug within one locale', function (): void {
    Article::factory()->create(['locale' => 'ar', 'slug' => 'foreign-property-ownership']);

    expect(fn () => Article::factory()->create([
        'locale' => 'ar',
        'slug' => 'foreign-property-ownership',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('resolves an article by locale and slug pair', function (): void {
    Article::factory()->create(['locale' => 'ar', 'slug' => 'tasi-record-volume']);
    $english = Article::factory()->create(['locale' => 'en', 'slug' => 'tasi-record-volume']);

    $found = Article::forLocale('en')->where('slug', 'tasi-record-volume')->sole();

    expect($found->id)->toBe($english->id);
});

it('applies the same rule to opportunities', function (): void {
    Opportunity::factory()->create(['locale' => 'ar', 'slug' => 'mining-licence-round']);
    Opportunity::factory()->create(['locale' => 'en', 'slug' => 'mining-licence-round']);

    expect(fn () => Opportunity::factory()->create([
        'locale' => 'ar',
        'slug' => 'mining-licence-round',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('stores transliterated latin slugs rather than encoded arabic', function (): void {
    $article = Article::factory()->create();

    expect($article->slug)->toMatch('/^[a-z0-9-]+$/')
        ->and($article->slug)->not->toContain('%');
});
