<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Fetchers;

use App\Models\Source;
use App\Services\Intelligence\FetchResult;
use App\Services\Intelligence\ParsedItem;
use Illuminate\Support\Carbon;

/**
 * A sitemap, ideally a Google News one.
 *
 * The weakest of the three: a plain sitemap gives a URL and a last-modified
 * date and no headline, so items land in the inbox titled by their slug until
 * an editor opens the link. Third in the order of preference for exactly that
 * reason — and still better than not watching a publisher at all.
 */
final class SitemapFetcher implements Fetcher
{
    public function __construct(private readonly HttpClient $http) {}

    public function fetch(Source $source): FetchResult
    {
        ['response' => $response, 'error' => $error, 'retryAfter' => $retryAfter] =
            $this->http->get($source, $source->pollUrl());

        if ($error !== null) {
            return FetchResult::failed($error, $retryAfter);
        }

        if ($response->status() === 304) {
            return FetchResult::notModified();
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($response->body());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false || ! isset($xml->url)) {
            return FetchResult::failed('no <url> entries in sitemap');
        }

        $items = [];
        $limit = (int) config('masar.intelligence.max_items_per_poll', 50);

        foreach ($xml->url as $entry) {
            if (count($items) >= $limit) {
                break;
            }

            $url = trim((string) ($entry->loc ?? ''));

            if ($url === '') {
                continue;
            }

            // Google News sitemaps carry a real title. Plain ones do not, and
            // the slug is the only honest stand-in we have.
            $news = $entry->children('news', true)->news ?? null;
            $title = trim((string) ($news->title ?? '')) ?: $this->titleFromUrl($url);

            $items[] = new ParsedItem(
                title: $title,
                url: $url,
                externalId: null,
                publishedAt: $this->date((string) ($news->publication_date ?? $entry->lastmod ?? '')),
                payload: ['loc' => $url],
            );
        }

        return FetchResult::ok($items, $response->header('ETag') ?: null, $response->header('Last-Modified') ?: null);
    }

    private function titleFromUrl(string $url): string
    {
        $slug = basename(parse_url($url, PHP_URL_PATH) ?: '');

        return trim(str_replace(['-', '_'], ' ', $slug)) ?: $url;
    }

    private function date(string $raw): ?Carbon
    {
        if (trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
