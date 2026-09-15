<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Events\ArticlePublished;
use App\Jobs\FlushCacheTags;
use App\Listeners\FlushArticleCaches;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The queue outlives the database.
 *
 * Redis is not rebuilt by `migrate:fresh`, so every job already queued against
 * the old rows is orphaned the moment the schema is reseeded. Draining a real
 * backlog for the first time produced 45 permanently failed jobs from exactly
 * that — all ModelNotFoundException on an article id that no longer existed.
 *
 * These drive the worker rather than inspecting a flag, because the first
 * attempt at this fix was a `$deleteWhenMissingModels` property that the queue
 * never reads for a listener, and a test that asserted the property would have
 * passed against a fix that did nothing.
 */
it('queues a cache flush that holds no model at all', function (): void {
    config(['queue.default' => 'database']);

    $article = Article::factory()->create(['status' => ArticleStatus::Published]);
    Event::dispatch(new ArticlePublished($article, User::factory()->create()));

    $payload = json_decode(DB::table('jobs')->sole()->payload, true);

    // The mechanism: an orphaned job is impossible if the payload has no model
    // reference to orphan.
    expect($payload['displayName'])->toBe(FlushCacheTags::class)
        ->and($payload['data']['command'])->not->toContain('App\Models\Article');
});

it('survives the article being deleted before the worker runs', function (): void {
    config(['queue.default' => 'database']);

    $article = Article::factory()->create(['status' => ArticleStatus::Published]);
    Event::dispatch(new ArticlePublished($article, User::factory()->create()));

    // Force-deleted, not soft-deleted: SerializesModels restores through
    // newQueryWithoutScopes, so a trashed article still resolves and would not
    // exercise this path. The row has to genuinely be gone — the state a
    // rebuilt database leaves every queued job in.
    $article->forceDelete();

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(DB::table('failed_jobs')->count())->toBe(0, 'an orphaned flush must not fail permanently')
        ->and(DB::table('jobs')->count())->toBe(0, 'and must not be left in the queue');
});

it('still flushes the caches an article appears on', function (): void {
    // The decoupling must not have turned the flush into a no-op.
    $article = Article::factory()->create(['status' => ArticleStatus::Published]);

    cache()->tags(['homepage'])->put('probe', 'stale', 60);
    expect(cache()->tags(['homepage'])->get('probe'))->toBe('stale');

    (new FlushArticleCaches)->handle(new ArticlePublished($article, User::factory()->create()));

    expect(cache()->tags(['homepage'])->get('probe'))->toBeNull();
});

it('computes the tags while the article still exists', function (): void {
    $article = Article::factory()->create(['status' => ArticleStatus::Published]);

    Illuminate\Support\Facades\Queue::fake();
    (new FlushArticleCaches)->handle(new ArticlePublished($article, User::factory()->create()));

    Illuminate\Support\Facades\Queue::assertPushed(
        FlushCacheTags::class,
        fn (FlushCacheTags $job): bool => in_array('homepage', $job->tags, true)
            && in_array('article:'.$article->getKey(), $job->tags, true)
            && in_array('locale:'.$article->locale, $job->tags, true),
    );
});
