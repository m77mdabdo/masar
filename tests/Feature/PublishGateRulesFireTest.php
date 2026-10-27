<?php

declare(strict_types=1);

use App\Actions\Articles\EvaluatePublishGate;
use App\Models\Article;

/**
 * Every gate rule, broken one at a time, must produce a failure.
 *
 * Reading the config proves a key is present; it does not prove the rule can
 * ever fire. A rule that is read and then compared against something always
 * true is the same class of thing as a policy with no caller — it looks like
 * enforcement and enforces nothing.
 */
beforeEach(function (): void {
    $this->gate = app(EvaluatePublishGate::class);
});

it('passes a fully prepared article', function (): void {
    expect(($this->gate)(publishableArticle()))->toBe([]);
});

dataset('broken articles', [
    'no summary' => [fn (Article $a) => $a->forceFill(['summary' => null])],
    'one summary point' => [fn (Article $a) => $a->forceFill(['summary' => ['نقطة واحدة فقط']])],
    'six summary points' => [fn (Article $a) => $a->forceFill([
        'summary' => ['أ', 'ب', 'ج', 'د', 'هـ', 'و'],
    ])],
    'no why it matters' => [fn (Article $a) => $a->forceFill(['why_it_matters' => null])],
    'no hero image' => [fn (Article $a) => $a->forceFill(['hero_media_id' => null])],
    'no hero alt' => [fn (Article $a) => $a->forceFill(['hero_alt' => null])],
    'sponsored with no sponsor' => [fn (Article $a) => $a->forceFill([
        'is_sponsored' => true,
        'sponsor_name' => null,
    ])],
    'never fact-checked' => [fn (Article $a) => $a->forceFill(['fact_checked_at' => null])],
]);

it('reports a failure for each rule, broken one at a time', function (Closure $break): void {
    $article = publishableArticle();

    expect(($this->gate)($article))->toBe([], 'the fixture was not publishable to begin with');

    $break($article);
    $article->save();

    expect(($this->gate)($article->fresh()))->not->toBe([]);
})->with('broken articles');

it('reports a failure when the only source has no url', function (): void {
    $article = publishableArticle();

    $article->sources()->update(['url' => null]);

    expect(($this->gate)($article->fresh()))->not->toBe([]);
});

it('reports a failure when there are no sources at all', function (): void {
    $article = publishableArticle();

    $article->sources()->delete();

    expect(($this->gate)($article->fresh()))->not->toBe([]);
});
