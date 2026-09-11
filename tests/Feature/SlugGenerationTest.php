<?php

declare(strict_types=1);

use App\Actions\Support\GenerateSlug;
use App\Actions\Support\TransliterateArabic;
use App\Models\Article;
use App\Models\Opportunity;

function slugger(): GenerateSlug
{
    return app(GenerateSlug::class);
}

function translit(): TransliterateArabic
{
    return app(TransliterateArabic::class);
}

it('turns an arabic headline into a readable latin slug', function (): void {
    $slug = translit()('ارتفاع حجم التداول في السوق الرئيسية');

    expect($slug)->toMatch('/^[a-z0-9-]+$/')
        ->and($slug)->not->toContain('%')
        ->and($slug)->not->toBe('');
});

it('passes latin text through unchanged', function (): void {
    expect(translit()('Foreign Property Ownership'))->toBe('foreign-property-ownership');
});

it('handles a mixed arabic and latin headline', function (): void {
    $slug = translit()('شركة Aramco تعلن نتائجها');

    expect($slug)->toContain('aramco')
        ->and($slug)->toMatch('/^[a-z0-9-]+$/');
});

it('strips diacritics and tatweel', function (): void {
    // Same word with and without harakat must land on the same slug.
    expect(translit()('الْاقْتِصَاد'))->toBe(translit()('الاقتصاد'));
});

it('drops the definite article', function (): void {
    expect(translit()('الاقتصاد'))->toBe(translit()('اقتصاد'));
});

it('drops arabic stop words', function (): void {
    $slug = translit()('النمو في القطاع');

    expect($slug)->not->toContain('fy')
        ->and($slug)->toContain('qtaa');
});

it('converts arabic-indic digits to western numerals', function (): void {
    expect(translit()('نمو ٢٥ بالمئة'))->toContain('25');
});

it('strips punctuation rather than transliterating it', function (): void {
    expect(translit()('الاقتصاد: إلى أين؟'))->toMatch('/^[a-z0-9-]+$/');
});

it('trims to the limit on a word boundary', function (): void {
    $slug = translit()('ارتفاع حجم التداول في السوق الرئيسية إلى مستويات غير مسبوقة هذا العام', 30);

    expect(mb_strlen($slug))->toBeLessThanOrEqual(30)
        ->and($slug)->not->toEndWith('-');
});

it('returns an empty string when nothing survives', function (): void {
    expect(translit()('!!! ???'))->toBe('');
});

it('suffixes a duplicate slug within the same locale', function (): void {
    $headline = 'تملك الأجانب للعقار';
    $first = slugger()($headline, 'ar', Article::class);

    Article::factory()->create(['locale' => 'ar', 'slug' => $first]);

    expect(slugger()($headline, 'ar', Article::class))->toBe($first.'-2');
});

it('keeps counting past the first collision', function (): void {
    $headline = 'تملك الأجانب للعقار';
    $base = slugger()($headline, 'ar', Article::class);

    Article::factory()->create(['locale' => 'ar', 'slug' => $base]);
    Article::factory()->create(['locale' => 'ar', 'slug' => $base.'-2']);

    expect(slugger()($headline, 'ar', Article::class))->toBe($base.'-3');
});

it('allows the same slug in a different locale', function (): void {
    $headline = 'تملك الأجانب للعقار';
    $slug = slugger()($headline, 'ar', Article::class);

    Article::factory()->create(['locale' => 'ar', 'slug' => $slug]);

    expect(slugger()($headline, 'en', Article::class))->toBe($slug);
});

it('lets an article keep its own slug on update', function (): void {
    $headline = 'تملك الأجانب للعقار';
    $slug = slugger()($headline, 'ar', Article::class);
    $article = Article::factory()->create(['locale' => 'ar', 'slug' => $slug]);

    expect(slugger()($headline, 'ar', Article::class, $article->id))->toBe($slug);
});

it('avoids a slug held by a soft deleted row', function (): void {
    // The unique index still contains it, so reusing it would fail on insert.
    $headline = 'تملك الأجانب للعقار';
    $slug = slugger()($headline, 'ar', Article::class);

    Article::factory()->create(['locale' => 'ar', 'slug' => $slug])->delete();

    expect(slugger()($headline, 'ar', Article::class))->toBe($slug.'-2');
});

it('keeps the suffixed slug inside the length limit', function (): void {
    $headline = 'ارتفاع حجم التداول في السوق الرئيسية إلى مستويات غير مسبوقة هذا العام';
    $base = slugger()($headline, 'ar', Article::class, null, 20);

    Article::factory()->create(['locale' => 'ar', 'slug' => $base]);

    $next = slugger()($headline, 'ar', Article::class, null, 20);

    expect(mb_strlen($next))->toBeLessThanOrEqual(20)
        ->and($next)->not->toBe($base);
});

it('falls back to a usable slug when transliteration yields nothing', function (): void {
    expect(slugger()('!!!', 'ar', Article::class))->toBe('article');
});

it('works for any model with a locale and slug', function (): void {
    $slug = slugger()('فرصة استثمارية في قطاع التعدين', 'ar', Opportunity::class);

    Opportunity::factory()->create(['locale' => 'ar', 'slug' => $slug]);

    expect(slugger()('فرصة استثمارية في قطاع التعدين', 'ar', Opportunity::class))->toBe($slug.'-2');
});

it('never produces url-encoded arabic', function (): void {
    foreach (['السعودية', 'الأسواق المالية', 'ريادة الأعمال'] as $headline) {
        expect(slugger()($headline, 'ar', Article::class))->not->toContain('%');
    }
});
