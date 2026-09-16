<?php

declare(strict_types=1);

use App\Models\Source;
use App\Services\Intelligence\Fetchers\HttpClient;
use App\Services\Intelligence\Fetchers\RobotsPolicy;
use Illuminate\Support\Facades\Http;

/**
 * We are a guest on someone else's server.
 *
 * This was documented as implemented and was not: the fetcher sent a polite
 * User-Agent naming us, and then ignored the one file whose purpose is to say
 * where not to go — which is worse than an anonymous crawler, because it is
 * rude under its own name.
 */
function robots(string $body, int $status = 200): void
{
    Http::fake([
        '*/robots.txt' => Http::response($body, $status, ['Content-Type' => 'text/plain']),
        '*' => Http::response('<rss></rss>', 200),
    ]);
}

it('refuses a path the wildcard group disallows', function (): void {
    robots("User-agent: *\nDisallow: /private/\n");

    $policy = app(RobotsPolicy::class);

    expect($policy->allows('https://example.test/private/feed.xml'))->toBeFalse()
        ->and($policy->allows('https://example.test/news/feed.xml'))->toBeTrue();
});

it('prefers a group naming us over the wildcard', function (): void {
    robots("User-agent: *\nDisallow: /\n\nUser-agent: MasarBot\nDisallow:\n");

    expect(app(RobotsPolicy::class)->allows('https://example.test/feed.xml', 'MasarBot/1.0 (+https://masar.sa/bot)'))
        ->toBeTrue();
});

it('lets the longest matching rule win, and Allow win a tie', function (): void {
    robots("User-agent: *\nDisallow: /news/\nAllow: /news/feed.xml\n");

    $policy = app(RobotsPolicy::class);

    expect($policy->allows('https://example.test/news/feed.xml'))->toBeTrue()
        ->and($policy->allows('https://example.test/news/article-1'))->toBeFalse();
});

it('honours the $ anchor and the * wildcard', function (): void {
    robots("User-agent: *\nDisallow: /*.pdf$\n");

    $policy = app(RobotsPolicy::class);

    expect($policy->allows('https://example.test/reports/annual.pdf'))->toBeFalse()
        ->and($policy->allows('https://example.test/reports/annual.pdf.html'))->toBeTrue();
});

it('treats a missing robots.txt as no restrictions', function (): void {
    robots('', 404);

    expect(app(RobotsPolicy::class)->allows('https://example.test/feed.xml'))->toBeTrue();
});

it('refuses when robots.txt is unreachable rather than assuming permission', function (): void {
    // "Unreachable" is not "permitted". Guessing in our own favour is how a
    // crawler gets an IP range banned.
    robots('', 503);

    expect(app(RobotsPolicy::class)->allows('https://example.test/feed.xml'))->toBeFalse();
});

it('reads a stated crawl-delay', function (): void {
    robots("User-agent: *\nCrawl-delay: 20\nDisallow:\n");

    expect(app(RobotsPolicy::class)->crawlDelaySeconds('https://example.test/feed.xml'))->toBe(20);
});

it('does not send the request at all when robots refuses', function (): void {
    robots("User-agent: *\nDisallow: /\n");

    $source = Source::factory()->create(['feed_url' => 'https://example.test/feed.xml']);
    $result = app(HttpClient::class)->get($source, 'https://example.test/feed.xml');

    expect($result['response'])->toBeNull()
        ->and($result['error'])->toContain('robots.txt');

    // The mechanism, not just the outcome: the feed itself was never requested.
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/feed.xml'));
});

it('still fetches, with conditional GET headers, when robots allows', function (): void {
    robots("User-agent: *\nDisallow: /admin/\n");

    $source = Source::factory()->create([
        'feed_url' => 'https://example.test/feed.xml',
        'etag' => 'W/"abc"',
        'last_modified' => 'Wed, 10 Sep 2026 10:00:00 GMT',
    ]);

    app(HttpClient::class)->get($source, 'https://example.test/feed.xml');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/feed.xml')
        && $request->header('If-None-Match')[0] === 'W/"abc"'
        && $request->header('If-Modified-Since')[0] === 'Wed, 10 Sep 2026 10:00:00 GMT'
        && str_contains($request->header('User-Agent')[0], 'Masar'));
});
