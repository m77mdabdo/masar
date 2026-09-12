<?php

declare(strict_types=1);

use App\Actions\Articles\EvaluatePublishGate;
use App\Models\Article;

/**
 * Each of the six gate rules must fail on its own, so an editor fixing one
 * problem is never surprised by a second one they were not told about.
 */
it('passes a complete article', function (): void {
    expect(publishableArticle()->isPublishable())->toBe([]);
});

it('blocks an article with no summary', function (): void {
    $failures = publishableArticle(['summary' => null])->isPublishable();

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('ملخص الثلاثين ثانية');
});

it('blocks a summary with too few points', function (): void {
    expect(publishableArticle(['summary' => ['نقطة وحيدة']])->isPublishable())->toHaveCount(1);
});

it('blocks a summary with too many points', function (): void {
    expect(publishableArticle(['summary' => ['1', '2', '3', '4', '5', '6']])->isPublishable())
        ->toHaveCount(1);
});

it('ignores blank summary points when counting', function (): void {
    expect(publishableArticle(['summary' => ['نقطة أولى', '', '   ', null]])->isPublishable())
        ->toHaveCount(1);
});

it('blocks an article with no source at all', function (): void {
    $article = publishableArticle();
    $article->sources()->delete();

    $failures = $article->fresh()->isPublishable();

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('مصدر');
});

it('blocks an article whose only source has no url', function (): void {
    $article = publishableArticle();
    $article->sources()->update(['url' => null]);

    expect($article->fresh()->isPublishable())->toHaveCount(1);
});

it('blocks an article with no hero image', function (): void {
    // Every listing surface renders a hero; an article without one breaks the grid.
    $failures = publishableArticle(['hero_media_id' => null])->isPublishable();

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('صورة غلاف');
});

it('blocks a hero image that has no alt text', function (): void {
    $failures = publishableArticle(['hero_alt' => null])->isPublishable();

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('نصًا بديلًا');
});

it('demands alt text even though the image itself is also missing', function (): void {
    // Two separate failures, so the editor is told about both at once rather
    // than adding an image and then discovering a second problem.
    expect(publishableArticle(['hero_media_id' => null, 'hero_alt' => null])->isPublishable())
        ->toHaveCount(2);
});

it('accepts a hero image that has alt text', function (): void {
    expect(publishableArticle(['hero_alt' => 'وصف للصورة.'])->isPublishable())->toBe([]);
});

it('blocks an article that has not been fact checked', function (): void {
    $failures = publishableArticle(['fact_checked_at' => null])->isPublishable();

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('تدقيق');
});

it('blocks an article with no why-it-matters', function (): void {
    $failures = publishableArticle(['why_it_matters' => null])->isPublishable();

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('لماذا يهم هذا');
});

it('blocks sponsored content with no sponsor name', function (): void {
    $failures = publishableArticle(['is_sponsored' => true, 'sponsor_name' => null])->isPublishable();

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('الراعية');
});

it('accepts sponsored content that names its sponsor', function (): void {
    expect(publishableArticle([
        'is_sponsored' => true,
        'sponsor_name' => 'مجموعة الأفق القابضة',
    ])->isPublishable())->toBe([]);
});

it('reports every failure at once rather than stopping at the first', function (): void {
    $article = Article::factory()->create([
        'summary' => null,
        'why_it_matters' => null,
        'fact_checked_at' => null,
        'is_sponsored' => true,
        'sponsor_name' => null,
        'hero_media_id' => null,
        'hero_alt' => null,
    ]);

    // All seven: summary, source, hero image, hero alt, fact check,
    // why it matters, sponsor name.
    expect($article->isPublishable())->toHaveCount(7);
});

it('honours a disabled rule in config', function (): void {
    config(['masar.publish_gate.require_fact_check' => false]);

    expect(publishableArticle(['fact_checked_at' => null])->isPublishable())->toBe([]);
});

it('honours a disabled hero image rule in config', function (): void {
    config(['masar.publish_gate.require_hero_image' => false]);

    expect(publishableArticle(['hero_media_id' => null])->isPublishable())->toBe([]);
});

it('gives the same answer through the model and the action', function (): void {
    $article = publishableArticle(['summary' => null]);

    expect($article->isPublishable())->toBe(app(EvaluatePublishGate::class)($article));
});

it('writes every failure message in arabic and as an instruction', function (): void {
    $article = Article::factory()->create([
        'summary' => null,
        'why_it_matters' => null,
        'fact_checked_at' => null,
    ]);

    foreach ($article->isPublishable() as $message) {
        expect(preg_match('/\p{Arabic}/u', $message))->toBe(1)
            ->and($message)->not->toContain('null');
    }
});
