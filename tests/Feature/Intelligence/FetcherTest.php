<?php

declare(strict_types=1);

use App\Enums\SourceType;
use App\Models\Source;
use App\Services\Intelligence\Fetchers\ApiFetcher;
use App\Services\Intelligence\Fetchers\RssFetcher;
use App\Services\Intelligence\Fetchers\SitemapFetcher;
use Illuminate\Support\Facades\Http;

/**
 * Fetchers against recorded fixtures. No live network: a test that depends on a
 * ministry's uptime is a test that fails for reasons nobody can fix.
 */
function fixture(string $name): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Intelligence/{$name}"));
}

it('parses an rss feed into items', function (): void {
    Http::fake(['*' => Http::response(fixture('ministry.rss.xml'), 200, ['ETag' => 'W/"abc"'])]);

    $result = app(RssFetcher::class)->fetch(Source::factory()->create());

    expect($result->ok)->toBeTrue()
        // Three entries, one with no title: an entry an editor cannot act on is
        // dropped rather than put in the inbox empty every poll.
        ->and($result->items)->toHaveCount(2)
        ->and($result->etag)->toBe('W/"abc"');

    expect($result->items[0]->title)->toBe('الوزارة تعتمد تعديلات على لوائح التراخيص الصناعية')
        ->and($result->items[0]->externalId)->toBe('gov-2026-0914-001')
        ->and($result->items[0]->author)->toBe('المركز الإعلامي')
        ->and($result->items[0]->publishedAt?->toDateTimeString())->toBe('2026-09-14 05:30:00');
});

it('parses an atom feed, including the alternate link and the name element', function (): void {
    Http::fake(['*' => Http::response(fixture('agency.atom.xml'), 200)]);

    $result = app(RssFetcher::class)->fetch(Source::factory()->create(['type' => SourceType::Atom]));

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0]->url)->toBe('https://agency.test/2026/09/appointment')
        ->and($result->items[0]->author)->toBe('التحرير');
});

it('parses a json api through the source parser config', function (): void {
    Http::fake(['*' => Http::response(fixture('authority.api.json'), 200)]);

    $source = Source::factory()->create([
        'type' => SourceType::Api,
        'parser_config' => [
            'items_path' => 'data.results',
            'fields' => [
                'title' => 'headline',
                'url' => 'links.self',
                'published_at' => 'date',
                'summary' => 'abstract',
                'external_id' => 'id',
            ],
        ],
    ]);

    $result = app(ApiFetcher::class)->fetch($source);

    expect($result->ok)->toBeTrue()
        ->and($result->items)->toHaveCount(1)
        ->and($result->items[0]->externalId)->toBe('AUTH-991')
        ->and($result->items[0]->url)->toBe('https://authority.test/releases/991');
});

it('reports a failure when items_path matches nothing', function (): void {
    Http::fake(['*' => Http::response(fixture('authority.api.json'), 200)]);

    $result = app(ApiFetcher::class)->fetch(Source::factory()->create([
        'type' => SourceType::Api,
        'parser_config' => ['items_path' => 'data.nowhere'],
    ]));

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('matched nothing');
});

it('takes a title from a news sitemap and falls back to the slug', function (): void {
    Http::fake(['*' => Http::response(fixture('news.sitemap.xml'), 200)]);

    $result = app(SitemapFetcher::class)->fetch(Source::factory()->create(['type' => SourceType::Sitemap]));

    expect($result->items)->toHaveCount(2)
        ->and($result->items[0]->title)->toBe('قواعد استثمار جديدة تدخل حيّز التنفيذ')
        // No news:title — the slug is the only honest stand-in we have.
        ->and($result->items[1]->title)->toBe('quiet growth story');
});

it('treats 304 as a success and not as a missing feed', function (): void {
    Http::fake(['*' => Http::response('', 304)]);

    $result = app(RssFetcher::class)->fetch(Source::factory()->create(['etag' => 'W/"abc"']));

    expect($result->ok)->toBeTrue()
        ->and($result->notModified)->toBeTrue()
        ->and($result->items)->toBe([]);
});

it('sends the conditional headers it was given', function (): void {
    Http::fake(['*' => Http::response(fixture('ministry.rss.xml'), 200)]);

    app(RssFetcher::class)->fetch(Source::factory()->create([
        'etag' => 'W/"abc"',
        'last_modified' => 'Mon, 14 Sep 2026 08:00:00 GMT',
    ]));

    // Asserting the mechanism, not the outcome: without these headers the
    // fetcher still "works", it just costs the publisher a full response every
    // poll — a failure with no visible symptom at our end.
    Http::assertSent(fn ($request) => $request->hasHeader('If-None-Match', 'W/"abc"')
        && $request->hasHeader('If-Modified-Since', 'Mon, 14 Sep 2026 08:00:00 GMT')
        && str_contains($request->header('User-Agent')[0], 'MasarBot'));
});

it('honours Retry-After rather than treating a rate limit as our error', function (): void {
    Http::fake(['*' => Http::response('', 429, ['Retry-After' => '120'])]);

    $result = app(RssFetcher::class)->fetch(Source::factory()->create());

    expect($result->ok)->toBeFalse()
        ->and($result->retryAfterSeconds)->toBe(120);
});

it('records malformed xml as a source failure rather than throwing', function (): void {
    Http::fake(['*' => Http::response('<rss><channel><item>', 200)]);

    $result = app(RssFetcher::class)->fetch(Source::factory()->create());

    expect($result->ok)->toBeFalse();
});
