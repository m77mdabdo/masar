<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Support\ViewCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * «الأكثر قراءة» used to mean "whatever the seeder wrote": `views_count` was
 * read by the homepage section and the admin widget, and written by nothing.
 *
 * This is the implementation. What it is tested for is mostly the ways the
 * naive version is wrong — a refresh counting twice, a crawler ranking a story,
 * a lifetime total that never changes, and a counting failure taking the page
 * down with it.
 */
function readableArticle(): Article
{
    // firstOrCreate: two articles in one test share the section, and a second
    // Category with the same slug is a unique-constraint failure.
    $category = Category::query()->firstWhere('slug', 'saudi')
        ?? Category::factory()->create(['slug' => 'saudi']);

    return publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => $category->getKey(),
    ]);
}

beforeEach(function (): void {
    // The suite has its own Redis databases and flushes them once at boot
    // (Tests\TestCase), so nothing survives from yesterday. This clears only
    // what a sibling test in this file may have left in the same run.
    Redis::connection()->del('views:buffer');
});

it('counts a read once per visitor, however many times they refresh', function (): void {
    // A refresh is not a read.
    $article = readableArticle();
    $url = "/ar/saudi/{$article->slug}";

    /*
     * The cookie is sent explicitly on every request, because the test client
     * does not feed a queued cookie back the way a browser returns one. Without
     * it each request arrives with no visitor id, the middleware issues a fresh
     * one, and three refreshes look like three people — which is exactly the
     * bug this test is about.
     */
    $visitor = '01HAAAAAAAAAAAAAAAAAAAAAAA';

    $this->withUnencryptedCookie('masar_vid', $visitor)->get($url)->assertOk();
    $this->withUnencryptedCookie('masar_vid', $visitor)->get($url)->assertOk();
    $this->withUnencryptedCookie('masar_vid', $visitor)->get($url)->assertOk();

    app(ViewCounter::class)->flush();

    expect((int) DB::table('article_views')->where('article_id', $article->getKey())->sum('views'))->toBe(1);
});

it('counts the same article again from a different visitor', function (): void {
    $article = readableArticle();
    $url = "/ar/saudi/{$article->slug}";

    $this->withUnencryptedCookie('masar_vid', '01HAAAAAAAAAAAAAAAAAAAAAAA')->get($url)->assertOk();

    // A different cookie is a different person.
    $this->withUnencryptedCookie('masar_vid', '01HZZZZZZZZZZZZZZZZZZZZZZZ')->get($url)->assertOk();

    app(ViewCounter::class)->flush();

    expect((int) DB::table('article_views')->where('article_id', $article->getKey())->sum('views'))->toBe(2);
});

it('does not count a crawler', function (): void {
    // A crawler fetching a real article is not a 404 and would otherwise rank
    // it — which is why this list is user agents, not the 404 log's paths.
    $article = readableArticle();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])
        ->get("/ar/saudi/{$article->slug}")->assertOk();

    app(ViewCounter::class)->flush();

    expect(DB::table('article_views')->count())->toBe(0);
});

it('buffers rather than writing on every request', function (): void {
    // §5: never increment() per request. The article page is the most-hit route.
    $article = readableArticle();

    $this->withUnencryptedCookie('masar_vid', '01HAAAAAAAAAAAAAAAAAAAAAAA')
        ->get("/ar/saudi/{$article->slug}")->assertOk();

    expect((int) $article->fresh()->views_count)->toBe(0)
        ->and(Redis::connection()->hgetall('views:buffer'))->not->toBeEmpty();
});

it('accumulates across flushes on the same day rather than overwriting', function (): void {
    $article = readableArticle();
    $counter = app(ViewCounter::class);

    $this->withUnencryptedCookie('masar_vid', '01HAAAAAAAAAAAAAAAAAAAAAAA')
        ->get("/ar/saudi/{$article->slug}")->assertOk();
    $counter->flush();

    $this->withUnencryptedCookie('masar_vid', '01HYYYYYYYYYYYYYYYYYYYYYYY')
        ->get("/ar/saudi/{$article->slug}")->assertOk();
    $counter->flush();

    expect((int) DB::table('article_views')->where('article_id', $article->getKey())->sum('views'))->toBe(2)
        ->and((int) $article->fresh()->views_count)->toBe(2);
});

it('ranks on the window, so a lifetime winner does not hold the slot forever', function (): void {
    $old = readableArticle();
    $recent = readableArticle();

    // The old story was read far more, but not lately.
    DB::table('article_views')->insert([
        ['article_id' => $old->getKey(), 'viewed_on' => now()->subDays(60)->toDateString(), 'views' => 5000, 'created_at' => now(), 'updated_at' => now()],
        ['article_id' => $recent->getKey(), 'viewed_on' => now()->toDateString(), 'views' => 10, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $days = (int) config('masar.views.window_days', 7);

    $ranked = DB::table('article_views')
        ->where('viewed_on', '>=', now()->subDays($days)->toDateString())
        ->selectRaw('article_id, SUM(views) as total')
        ->groupBy('article_id')->orderByDesc('total')->pluck('article_id')->all();

    expect($ranked)->toBe([$recent->getKey()]);
});

it('never lets a counting failure reach the reader', function (): void {
    // An article page that 500s because a number could not be written is a
    // worse outcome than an uncounted read, every time.
    $article = readableArticle();

    Redis::shouldReceive('connection')->andThrow(new RuntimeException('redis is down'));

    $this->get("/ar/saudi/{$article->slug}")->assertOk();
});

it('survives an article that vanished between the read and the flush', function (): void {
    // Without this one missing row throws a foreign-key error that kills the
    // whole flush, so every article's counts are lost on every run until
    // someone clears the buffer by hand. It is not hypothetical: Redis is
    // shared between the test and development databases, so ids from one
    // routinely appear in the other's buffer.
    $article = readableArticle();

    $this->withUnencryptedCookie('masar_vid', '01HAAAAAAAAAAAAAAAAAAAAAAA')
        ->get("/ar/saudi/{$article->slug}")->assertOk();

    $survivor = readableArticle();
    $this->withUnencryptedCookie('masar_vid', '01HBBBBBBBBBBBBBBBBBBBBBBB')
        ->get("/ar/saudi/{$survivor->slug}")->assertOk();

    $article->forceDelete();

    app(ViewCounter::class)->flush();

    // The survivor was still written, and the orphan was dropped rather than
    // left to poison every future flush.
    expect((int) DB::table('article_views')->where('article_id', $survivor->getKey())->sum('views'))->toBe(1)
        ->and(DB::table('article_views')->count())->toBe(1);
});
