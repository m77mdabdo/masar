<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;

/**
 * The transition map is the editorial spine. These tests cover it exhaustively:
 * every declared edge must be allowed, and every edge NOT declared must be
 * rejected — the second half is what actually protects the workflow.
 */

/**
 * @return array<string, array<int, string>>
 */
function transitionMap(): array
{
    return [
        'idea' => ['assigned', 'rejected'],
        'assigned' => ['research', 'writing', 'on_hold', 'rejected'],
        'research' => ['writing', 'on_hold'],
        'writing' => ['fact_check', 'on_hold'],
        'fact_check' => ['editor_review', 'needs_revision'],
        'editor_review' => ['seo', 'needs_revision'],
        'seo' => ['ready', 'needs_revision'],
        'ready' => ['scheduled', 'published', 'needs_revision'],
        'scheduled' => ['published', 'ready'],
        'published' => ['archived', 'needs_revision'],
        'needs_revision' => ['writing', 'editor_review'],
        'on_hold' => ['writing', 'rejected'],
        'rejected' => ['idea'],
        'archived' => ['published'],
    ];
}

it('covers every status in the transition map', function (): void {
    $mapped = array_keys(transitionMap());
    $cases = array_column(ArticleStatus::cases(), 'value');

    sort($mapped);
    sort($cases);

    expect($mapped)->toBe($cases);
});

it('allows every declared transition', function (): void {
    foreach (transitionMap() as $from => $targets) {
        $status = ArticleStatus::from($from);

        foreach ($targets as $target) {
            expect($status->canTransitionTo(ArticleStatus::from($target)))
                ->toBeTrue("{$from} → {$target} should be allowed");
        }
    }
});

it('rejects every transition that is not declared', function (): void {
    $all = array_column(ArticleStatus::cases(), 'value');

    foreach (transitionMap() as $from => $allowed) {
        $status = ArticleStatus::from($from);
        $forbidden = array_diff($all, $allowed);

        foreach ($forbidden as $target) {
            expect($status->canTransitionTo(ArticleStatus::from($target)))
                ->toBeFalse("{$from} → {$target} should be rejected");
        }
    }
});

it('never allows a status to transition to itself', function (): void {
    foreach (ArticleStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse();
    }
});

it('rejects the shortcuts that would skip fact checking', function (): void {
    expect(ArticleStatus::Writing->canTransitionTo(ArticleStatus::Published))->toBeFalse()
        ->and(ArticleStatus::Idea->canTransitionTo(ArticleStatus::Published))->toBeFalse()
        ->and(ArticleStatus::Research->canTransitionTo(ArticleStatus::Ready))->toBeFalse()
        ->and(ArticleStatus::EditorReview->canTransitionTo(ArticleStatus::Published))->toBeFalse();
});

it('exposes allowedTransitions as enum instances', function (): void {
    $transitions = ArticleStatus::Ready->allowedTransitions();

    expect($transitions)->each->toBeInstanceOf(ArticleStatus::class)
        ->and($transitions)->toContain(ArticleStatus::Published);
});

it('gives every status an Arabic label', function (): void {
    foreach (ArticleStatus::cases() as $status) {
        expect($status->label())->not->toBeEmpty()
            ->and(preg_match('/\p{Arabic}/u', $status->label()))->toBe(1);
    }
});
