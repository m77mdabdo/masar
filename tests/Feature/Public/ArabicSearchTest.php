<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Queries\SearchArticlesQuery;
use App\Support\ArabicNormaliser;

function normalise(string $text): string
{
    return app(ArabicNormaliser::class)($text);
}

function searchFor(string $term)
{
    return app(SearchArticlesQuery::class)($term, 'ar');
}

function arabicArticle(string $title): Article
{
    return Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'title' => $title,
        'category_id' => Category::factory()->create()->id,
    ]);
}

/**
 * Arabic readers type the same word many ways. The index and the query are both
 * folded so they meet in the middle.
 */
it('folds the definite article away', function (): void {
    expect(normalise('الاستثمار'))->toBe(normalise('استثمار'));
});

it('folds hamza variants onto bare alef', function (): void {
    expect(normalise('إستثمار'))->toBe(normalise('استثمار'))
        ->and(normalise('أستثمار'))->toBe(normalise('استثمار'))
        ->and(normalise('آستثمار'))->toBe(normalise('استثمار'));
});

it('strips diacritics', function (): void {
    expect(normalise('اسْتِثْمَار'))->toBe(normalise('استثمار'));
});

it('strips tatweel', function (): void {
    expect(normalise('شركـــة'))->toBe(normalise('شركة'));
});

it('folds taa marbuta to haa', function (): void {
    expect(normalise('مكتبة'))->toBe(normalise('مكتبه'));
});

it('folds alef maqsura to yaa', function (): void {
    expect(normalise('مستوى'))->toBe(normalise('مستوي'));
});

it('converts arabic-indic digits to western', function (): void {
    expect(normalise('رؤية ٢٠٣٠'))->toContain('2030');
});

it('keeps a short word that merely starts with alef-lam', function (): void {
    // "الله" must not become "ه".
    expect(normalise('الله'))->not->toBe('ه');
});

it('matches a bare query against a prefixed headline', function (): void {
    $article = arabicArticle('ارتفاع الاستثمار الأجنبي في السوق السعودية');

    // The whole point: "استثمار" must find "الاستثمار".
    expect(searchFor('استثمار')->pluck('id')->all())->toContain($article->id);
});

it('matches a prefixed query against a bare headline', function (): void {
    $article = arabicArticle('نمو استثمار القطاع الخاص');

    expect(searchFor('الاستثمار')->pluck('id')->all())->toContain($article->id);
});

it('matches across hamza spellings', function (): void {
    $article = arabicArticle('تقرير عن الأسواق المالية');

    expect(searchFor('الاسواق')->pluck('id')->all())->toContain($article->id);
});

it('returns nothing for an empty query', function (): void {
    arabicArticle('أي مادة');

    expect(searchFor('   ')->total())->toBe(0);
});

it('excludes drafts from results', function (): void {
    $draft = Article::factory()->draft()->create(['locale' => 'ar', 'title' => 'الاستثمار المؤجل']);

    expect(searchFor('استثمار')->pluck('id')->all())->not->toContain($draft->id);
});

it('renders the search page with a real arabic query', function (): void {
    $article = arabicArticle('مؤشرات الاستثمار الصناعي');

    $this->get('/ar/search?q='.rawurlencode('استثمار'))
        ->assertOk()
        ->assertSee($article->title, false);
});
