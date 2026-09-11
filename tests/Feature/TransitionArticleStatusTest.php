<?php

declare(strict_types=1);

use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Events\ArticlePublished;
use App\Events\ArticleStatusChanged;
use App\Exceptions\InvalidStatusTransition;
use App\Exceptions\PublishGateFailed;
use App\Models\Article;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

function transition(): TransitionArticleStatus
{
    return app(TransitionArticleStatus::class);
}

it('moves an article along a legal edge', function (): void {
    $chief = staff('editor_in_chief');
    $article = Article::factory()->create(['status' => ArticleStatus::Idea]);

    $result = transition()($article, ArticleStatus::Assigned, $chief);

    expect($result->status)->toBe(ArticleStatus::Assigned)
        ->and($article->fresh()->status)->toBe(ArticleStatus::Assigned);
});

it('refuses an edge that is not in the transition map', function (): void {
    $chief = staff('editor_in_chief');
    $article = Article::factory()->create(['status' => ArticleStatus::Writing]);

    expect(fn () => transition()($article, ArticleStatus::Published, $chief))
        ->toThrow(InvalidStatusTransition::class);

    expect($article->fresh()->status)->toBe(ArticleStatus::Writing);
});

it('refuses an actor without transition permission', function (): void {
    // social has article.view and media.manage, but not article.transition.
    $social = staff('social');
    $article = Article::factory()->create(['status' => ArticleStatus::Idea]);

    expect(fn () => transition()($article, ArticleStatus::Assigned, $social))
        ->toThrow(AuthorizationException::class);

    expect($article->fresh()->status)->toBe(ArticleStatus::Idea);
});

it('refuses a writer transitioning someone else\'s article', function (): void {
    $writer = staff('writer');
    $article = Article::factory()->create(['status' => ArticleStatus::Idea]);

    expect(fn () => transition()($article, ArticleStatus::Assigned, $writer))
        ->toThrow(AuthorizationException::class);
});

it('lets a writer move their own article', function (): void {
    $writer = staff('writer');
    $article = Article::factory()->create([
        'status' => ArticleStatus::Idea,
        'author_id' => $writer->id,
    ]);

    expect(transition()($article, ArticleStatus::Assigned, $writer)->status)
        ->toBe(ArticleStatus::Assigned);
});

it('stamps fact_checked_at when leaving fact check for editor review', function (): void {
    $chief = staff('editor_in_chief');
    $article = Article::factory()->create([
        'status' => ArticleStatus::FactCheck,
        'fact_checked_at' => null,
        'fact_checker_id' => null,
    ]);

    $result = transition()($article, ArticleStatus::EditorReview, $chief);

    expect($result->fact_checked_at)->not->toBeNull()
        ->and($result->fact_checker_id)->toBe($chief->id);
});

it('does not stamp fact_checked_at on unrelated transitions', function (): void {
    $chief = staff('editor_in_chief');
    $article = Article::factory()->create([
        'status' => ArticleStatus::Writing,
        'fact_checked_at' => null,
    ]);

    expect(transition()($article, ArticleStatus::FactCheck, $chief)->fact_checked_at)->toBeNull();
});

it('runs the publish gate when the target is published', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'summary' => null]);

    expect(fn () => transition()($article, ArticleStatus::Published, $chief))
        ->toThrow(PublishGateFailed::class);

    expect($article->fresh()->status)->toBe(ArticleStatus::Ready);
});

it('carries the gate reasons on the exception', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'summary' => null]);

    try {
        transition()($article, ArticleStatus::Published, $chief);
    } catch (PublishGateFailed $e) {
        expect($e->reasons())->toHaveCount(1)
            ->and($e->reasons()[0])->toContain('ملخص');

        return;
    }

    $this->fail('PublishGateFailed was not thrown.');
});

it('publishes an article that satisfies the gate', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    expect(transition()($article, ArticleStatus::Published, $chief)->status)
        ->toBe(ArticleStatus::Published);
});

it('dispatches ArticleStatusChanged on every transition', function (): void {
    Event::fake([ArticleStatusChanged::class, ArticlePublished::class]);

    $chief = staff('editor_in_chief');
    $article = Article::factory()->create(['status' => ArticleStatus::Idea]);

    transition()($article, ArticleStatus::Assigned, $chief, 'أُسندت للكاتب');

    Event::assertDispatched(ArticleStatusChanged::class, function (ArticleStatusChanged $e) use ($article): bool {
        return $e->article->is($article)
            && $e->from === ArticleStatus::Idea
            && $e->to === ArticleStatus::Assigned
            && $e->note === 'أُسندت للكاتب';
    });

    Event::assertNotDispatched(ArticlePublished::class);
});

it('dispatches ArticlePublished only when reaching published', function (): void {
    Event::fake([ArticlePublished::class]);

    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    transition()($article, ArticleStatus::Published, $chief);

    Event::assertDispatched(ArticlePublished::class);
});

it('writes an audit entry for the transition', function (): void {
    $chief = staff('editor_in_chief');
    $article = Article::factory()->create(['status' => ArticleStatus::Idea]);

    transition()($article, ArticleStatus::Assigned, $chief);

    $entry = Activity::query()->where('log_name', 'article.status')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties['from'])->toBe('idea')
        ->and($entry->properties['to'])->toBe('assigned')
        ->and((int) $entry->causer_id)->toBe($chief->id);
});

it('does not persist a status when the gate rejects it', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'why_it_matters' => null]);

    try {
        transition()($article, ArticleStatus::Published, $chief);
    } catch (PublishGateFailed) {
        // expected
    }

    expect($article->fresh()->status)->toBe(ArticleStatus::Ready)
        ->and(Activity::query()->where('log_name', 'article.status')->count())->toBe(0);
});
