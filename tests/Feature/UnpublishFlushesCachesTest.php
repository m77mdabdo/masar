<?php

declare(strict_types=1);

use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Events\ArticleUnpublished;
use App\Jobs\FlushCacheTags;
use App\Models\Article;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Leaving `published` must invalidate every surface the article sits on.
 *
 * This was announced by an action nothing called, so the panel's own archive
 * button flushed nothing and a story stayed on the homepage after being taken
 * down. These tests drive the transition — the only path that writes the
 * column — rather than the action that used to own the announcement.
 */
it('announces an unpublish when a published article is archived', function (): void {
    Event::fake([ArticleUnpublished::class]);

    $article = publishableArticle(['status' => ArticleStatus::Published, 'published_at' => now()]);
    $actor = staff('editor_in_chief');

    app(TransitionArticleStatus::class)($article, ArticleStatus::Archived, $actor, 'سُحبت بطلب المصدر');

    Event::assertDispatched(
        ArticleUnpublished::class,
        fn (ArticleUnpublished $e): bool => $e->article->is($article)
            && $e->actor->is($actor)
            && $e->reason === 'سُحبت بطلب المصدر',
    );
});

it('announces an unpublish when a published article is sent back for revision', function (): void {
    Event::fake([ArticleUnpublished::class]);

    $article = publishableArticle(['status' => ArticleStatus::Published, 'published_at' => now()]);

    app(TransitionArticleStatus::class)(
        $article,
        ArticleStatus::NeedsRevision,
        staff('editor_in_chief'),
        'الرقم في الفقرة الثالثة خاطئ',
    );

    Event::assertDispatched(ArticleUnpublished::class);
});

it('queues a cache flush carrying the article\'s surfaces', function (): void {
    Queue::fake();

    $article = publishableArticle(['status' => ArticleStatus::Published, 'published_at' => now()]);

    app(TransitionArticleStatus::class)($article, ArticleStatus::Archived, staff('editor_in_chief'), 'سبب');

    Queue::assertPushed(FlushCacheTags::class, function (FlushCacheTags $job) use ($article): bool {
        $tags = (fn () => $this->tags)->call($job);

        return in_array('homepage', $tags, true)
            && in_array('articles', $tags, true)
            && in_array('article:'.$article->getKey(), $tags, true)
            && in_array('category:'.$article->category_id, $tags, true);
    });
});

it('does not announce an unpublish for a transition that never reached publication', function (): void {
    Event::fake([ArticleUnpublished::class]);

    $article = Article::factory()->create(['status' => ArticleStatus::Writing]);

    app(TransitionArticleStatus::class)($article, ArticleStatus::FactCheck, staff('editor_in_chief'));

    Event::assertNotDispatched(ArticleUnpublished::class);
});

it('does not announce an unpublish when an archived article is republished', function (): void {
    Event::fake([ArticleUnpublished::class]);

    $article = publishableArticle(['status' => ArticleStatus::Archived, 'published_at' => now()->subWeek()]);

    app(TransitionArticleStatus::class)($article, ArticleStatus::Published, staff('editor_in_chief'));

    Event::assertNotDispatched(ArticleUnpublished::class);
});
