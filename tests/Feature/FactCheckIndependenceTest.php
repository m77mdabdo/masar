<?php

declare(strict_types=1);

use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * A writer cannot fact-check their own article.
 *
 * The editorial standards page states this as a system guarantee, and for the
 * life of the project it was not one. `ArticlePolicy::factCheck()` implemented
 * the rule correctly and **nothing called it** — its only references were its
 * own tests. The transition that stamps the fact-check authorised `transition`,
 * which returns true when the actor owns the article, so a writer moved their
 * own piece out of fact_check and was recorded as its fact-checker.
 *
 * **Every test here drives the real transition path.** A policy unit test is
 * what concealed this: it passed throughout, because the rule was right and
 * unreachable. Testing the rule is not testing the path.
 */
function factCheckable(User $author): App\Models\Article
{
    return publishableArticle([
        'status' => ArticleStatus::FactCheck,
        'author_id' => $author->getKey(),
        'fact_checked_at' => null,
        'fact_checker_id' => null,
    ]);
}

it('refuses to let the author move their own article out of fact-check', function (): void {
    $writer = staff('editor_in_chief'); // has both transition and factcheck rights
    $article = factCheckable($writer);

    expect(fn () => app(TransitionArticleStatus::class)($article, ArticleStatus::EditorReview, $writer))
        ->toThrow(AuthorizationException::class);

    $article->refresh();
    expect($article->status)->toBe(ArticleStatus::FactCheck)
        ->and($article->fact_checked_at)->toBeNull()
        ->and($article->fact_checker_id)->toBeNull();
});

it('refuses a credited co-author too', function (): void {
    // Ownership is the byline or a credited co-author — the same definition the
    // policy uses. A co-author checking the piece is the same conflict.
    $author = staff('writer');
    $coAuthor = staff('editor_in_chief');

    $article = factCheckable($author);
    $article->coAuthors()->attach($coAuthor->getKey());

    expect(fn () => app(TransitionArticleStatus::class)($article->fresh(), ArticleStatus::EditorReview, $coAuthor))
        ->toThrow(AuthorizationException::class);
});

it('lets somebody else do it, and records who', function (): void {
    $author = staff('writer');
    $checker = staff('editor_in_chief');
    $article = factCheckable($author);

    $result = app(TransitionArticleStatus::class)($article, ArticleStatus::EditorReview, $checker);

    expect($result->status)->toBe(ArticleStatus::EditorReview)
        ->and($result->fact_checked_at)->not->toBeNull()
        ->and((int) $result->fact_checker_id)->toBe($checker->getKey())
        ->and((int) $result->fact_checker_id)->not->toBe((int) $article->author_id);
});

it('still refuses when the actor holds every permission', function (): void {
    // This is an editorial rule, not a permission question. A super_admin who
    // wrote the article is still its writer.
    $admin = staff('super_admin');
    $article = factCheckable($admin);

    expect(fn () => app(TransitionArticleStatus::class)($article, ArticleStatus::EditorReview, $admin))
        ->toThrow(AuthorizationException::class);
});

it('does not block an author moving their article within other stages', function (): void {
    // The rule is about crossing the fact-check stage, not about the author
    // touching their own article at all.
    $writer = staff('writer');
    $article = publishableArticle([
        'status' => ArticleStatus::Writing,
        'author_id' => $writer->getKey(),
    ]);

    $result = app(TransitionArticleStatus::class)($article, ArticleStatus::FactCheck, $writer);

    expect($result->status)->toBe(ArticleStatus::FactCheck);
});

it('cannot be published without a fact-checker who is not the author', function (): void {
    // The end-to-end consequence: the gate requires fact_checked_at, which now
    // only a second person can produce.
    $author = staff('writer');
    $article = factCheckable($author);

    expect($article->fresh()->isPublishable())->not->toBeEmpty();
});
