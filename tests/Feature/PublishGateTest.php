<?php

declare(strict_types=1);

use App\Actions\Articles\EvaluatePublishGate;
use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

it('blocks a hero image that has no alt text', function (): void {
    $article = publishableArticle([
        'hero_media_id' => fakeMediaId(),
        'hero_alt' => null,
    ]);

    $failures = $article->isPublishable();

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('نصًا بديلًا');
});

it('does not demand alt text when there is no hero image', function (): void {
    // Nothing to describe, so the rule has nothing to say.
    expect(publishableArticle(['hero_media_id' => null, 'hero_alt' => null])->isPublishable())
        ->toBe([]);
});

it('accepts a hero image that has alt text', function (): void {
    expect(publishableArticle([
        'hero_media_id' => fakeMediaId(),
        'hero_alt' => 'وصف للصورة.',
    ])->isPublishable())->toBe([]);
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
        'hero_media_id' => fakeMediaId(),
        'hero_alt' => null,
    ]);

    // All six: summary, source, hero alt, fact check, why it matters, sponsor.
    expect($article->isPublishable())->toHaveCount(6);
});

it('honours a disabled rule in config', function (): void {
    config(['masar.publish_gate.require_fact_check' => false]);

    expect(publishableArticle(['fact_checked_at' => null])->isPublishable())->toBe([]);
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

/**
 * Minimal media row — the hero-alt rule only fires when an image exists.
 */
function fakeMediaId(): int
{
    return (int) DB::table('media')->insertGetId([
        'model_type' => 'article',
        'model_id' => 1,
        'uuid' => (string) Str::uuid(),
        'collection_name' => 'hero',
        'name' => 'hero',
        'file_name' => 'hero.webp',
        'mime_type' => 'image/webp',
        'disk' => 'public',
        'size' => 0,
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
