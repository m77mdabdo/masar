<?php

declare(strict_types=1);

use App\Actions\Articles\SchedulePublication;
use App\Enums\ArticleStatus;
use Spatie\Activitylog\Models\Activity;

function schedule(): SchedulePublication
{
    return app(SchedulePublication::class);
}

it('schedules a ready article for a future time', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready]);
    $when = now()->addDays(3);

    $result = schedule()($article, $when, $chief);

    expect($result->status)->toBe(ArticleStatus::Scheduled)
        ->and($result->scheduled_for->timestamp)->toBe($when->timestamp);
});

it('refuses a scheduled time in the past', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    expect(fn () => schedule()($article, now()->subHour(), $chief))
        ->toThrow(InvalidArgumentException::class);

    expect($article->fresh()->status)->toBe(ArticleStatus::Ready);
});

it('publishes an article whose time has come', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'editor_id' => $chief->id]);

    schedule()($article, now()->addMinutes(5), $chief);

    // Travel past the scheduled moment rather than writing a past date, which
    // SchedulePublication would have rejected.
    $this->travelTo(now()->addMinutes(10));

    $this->artisan('masar:publish-scheduled')->assertSuccessful();

    $article->refresh();

    expect($article->status)->toBe(ArticleStatus::Published)
        ->and($article->published_at)->not->toBeNull()
        ->and($article->scheduled_for)->toBeNull();
});

it('leaves an article whose time has not come', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'editor_id' => $chief->id]);

    schedule()($article, now()->addDays(2), $chief);

    $this->artisan('masar:publish-scheduled')->assertSuccessful();

    expect($article->fresh()->status)->toBe(ArticleStatus::Scheduled);
});

it('does not publish a scheduled article that no longer passes the gate', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'editor_id' => $chief->id]);

    schedule()($article, now()->addMinutes(5), $chief);

    // Someone edits the summary out after scheduling.
    $article->fresh()->forceFill(['summary' => null])->save();

    $this->travelTo(now()->addMinutes(10));
    $this->artisan('masar:publish-scheduled')->assertSuccessful();

    expect($article->fresh()->status)->toBe(ArticleStatus::Scheduled);
});

it('sets reading time when publishing on schedule', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle([
        'status' => ArticleStatus::Ready,
        'editor_id' => $chief->id,
        'reading_time' => null,
    ]);

    schedule()($article, now()->addMinutes(5), $chief);
    $this->travelTo(now()->addMinutes(10));
    $this->artisan('masar:publish-scheduled')->assertSuccessful();

    expect($article->fresh()->getRawOriginal('reading_time'))->toBeGreaterThan(0);
});

it('reports nothing to do when there is nothing due', function (): void {
    $this->artisan('masar:publish-scheduled')->assertSuccessful();
});

it('lists due articles without publishing them on a dry run', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'editor_id' => $chief->id]);

    schedule()($article, now()->addMinutes(5), $chief);
    $this->travelTo(now()->addMinutes(10));

    $this->artisan('masar:publish-scheduled', ['--dry-run' => true])->assertSuccessful();

    expect($article->fresh()->status)->toBe(ArticleStatus::Scheduled);
});

it('publishes through the action so the audit trail exists', function (): void {
    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'editor_id' => $chief->id]);

    schedule()($article, now()->addMinutes(5), $chief);
    $this->travelTo(now()->addMinutes(10));
    $this->artisan('masar:publish-scheduled')->assertSuccessful();

    expect(Activity::query()->where('log_name', 'article.published')->count())
        ->toBe(1);
});
