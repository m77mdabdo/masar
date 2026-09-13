<?php

declare(strict_types=1);

use App\Actions\Articles\TransitionArticleStatus;
use App\Actions\Articles\UpdateGateFailuresCount;
use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * The denormalised gate result is only worth having if it always agrees with the
 * gate itself. Every test here compares the stored number to a live evaluation.
 */
it('records zero for a publishable article', function (): void {
    $article = publishableArticle();

    app(UpdateGateFailuresCount::class)($article);

    expect($article->fresh()->gate_failures_count)->toBe(0);
});

it('records the number of unmet rules', function (): void {
    $article = publishableArticle(['summary' => null, 'why_it_matters' => null]);

    app(UpdateGateFailuresCount::class)($article);

    expect($article->fresh()->gate_failures_count)->toBe(2)
        ->and($article->fresh()->gate_failures_count)->toBe(count($article->fresh()->isPublishable()));
});

it('starts as null until something evaluates it', function (): void {
    // NULL means "never evaluated", which is not the same as "publishable".
    expect(Article::factory()->create()->gate_failures_count)->toBeNull();
});

it('updates on a status transition', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::FactCheck, 'fact_checked_at' => null]);

    app(UpdateGateFailuresCount::class)($article);
    expect($article->fresh()->gate_failures_count)->toBe(1);

    // Entering editor_review stamps fact_checked_at, which clears the failure.
    app(TransitionArticleStatus::class)($article, ArticleStatus::EditorReview, $chief);

    expect($article->fresh()->gate_failures_count)->toBe(0);
});

it('does not touch updated_at when writing the count', function (): void {
    $article = publishableArticle();
    $before = $article->fresh()->updated_at;

    $this->travelTo(now()->addMinutes(10));
    app(UpdateGateFailuresCount::class)($article);
    $this->travelBack();

    // A derived counter is not an edit and must not look like one.
    expect($article->fresh()->updated_at->timestamp)->toBe($before->timestamp);
});

it('does not write an audit entry', function (): void {
    $article = publishableArticle();
    $before = Activity::count();

    app(UpdateGateFailuresCount::class)($article);

    expect(Activity::count())->toBe($before);
});

it('is repaired by masar:recount', function (): void {
    $article = publishableArticle(['summary' => null]);

    DB::table('articles')->where('id', $article->id)->update(['gate_failures_count' => 99]);

    $this->artisan('masar:recount')->assertSuccessful();

    expect($article->fresh()->gate_failures_count)->toBe(1);
});

it('fills nulls on recount', function (): void {
    $article = publishableArticle();
    DB::table('articles')->where('id', $article->id)->update(['gate_failures_count' => null]);

    $this->artisan('masar:recount')->assertSuccessful();

    expect($article->fresh()->gate_failures_count)->toBe(0);
});

it('matches the PHP evaluation for every article', function (): void {
    publishableArticle();
    publishableArticle(['summary' => null]);
    publishableArticle(['hero_media_id' => null, 'why_it_matters' => null]);
    publishableArticle(['is_sponsored' => true, 'sponsor_name' => null]);

    $this->artisan('masar:recount')->assertSuccessful();

    $mismatched = Article::query()->with('sources')->get()->filter(
        fn (Article $a): bool => count($a->isPublishable()) !== (int) $a->gate_failures_count,
    );

    expect($mismatched)->toBeEmpty();
});

it('makes the SQL filter agree with the PHP gate', function (): void {
    publishableArticle(['status' => ArticleStatus::Ready]);
    publishableArticle(['status' => ArticleStatus::Ready, 'summary' => null]);
    publishableArticle(['status' => ArticleStatus::Ready, 'why_it_matters' => null]);

    $this->artisan('masar:recount')->assertSuccessful();

    $viaSql = Article::query()
        ->where('gate_failures_count', '>', 0)
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $viaPhp = Article::query()
        ->with('sources')
        ->get()
        ->filter(fn (Article $a): bool => $a->isPublishable() !== [])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($viaSql)->toBe($viaPhp)->and($viaSql)->toHaveCount(2);
});

it('excludes never-evaluated rows from the blocked filter', function (): void {
    $unknown = Article::factory()->create(['status' => ArticleStatus::Ready]);

    // NULL is not "blocked" — it is "unknown", and recount is what resolves it.
    expect(Article::query()->where('gate_failures_count', '>', 0)->pluck('id')->all())
        ->not->toContain($unknown->id);
});
