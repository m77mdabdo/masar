<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Fetchers;

use App\Models\Source;
use App\Services\Intelligence\FetchResult;
use App\Services\Intelligence\ParsedItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * A JSON API, described by the source's own `parser_config`.
 *
 * Every API shapes its response differently, so the mapping is data rather than
 * a class per publisher: an editor adding a ministry's API fills in where the
 * list lives and which key holds the title, and no deploy is needed.
 *
 *   {
 *     "items_path": "data.results",
 *     "fields": { "title": "headline", "url": "links.self", "published_at": "date" }
 *   }
 */
final class ApiFetcher implements Fetcher
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

        $body = $response->json();

        if (! is_array($body)) {
            return FetchResult::failed('response was not json');
        }

        $config = (array) ($source->parser_config ?? []);
        $rows = $this->rows($body, $config);

        if ($rows === null) {
            return FetchResult::failed('items_path "'.($config['items_path'] ?? '').'" matched nothing');
        }

        $items = [];
        $limit = (int) config('masar.intelligence.max_items_per_poll', 50);

        foreach ($rows as $row) {
            if (count($items) >= $limit) {
                break;
            }

            $parsed = $this->item((array) $row, $config);

            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return FetchResult::ok($items, $response->header('ETag') ?: null, $response->header('Last-Modified') ?: null);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $config
     * @return array<int, mixed>|null
     */
    private function rows(array $body, array $config): ?array
    {
        $path = $config['items_path'] ?? null;
        $rows = $path === null ? $body : Arr::get($body, (string) $path);

        return is_array($rows) ? array_values($rows) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $config
     */
    private function item(array $row, array $config): ?ParsedItem
    {
        $fields = (array) ($config['fields'] ?? []);
        $get = fn (string $name, string $default): mixed => Arr::get($row, (string) ($fields[$name] ?? $default));

        $title = trim((string) $get('title', 'title'));
        $url = trim((string) $get('url', 'url'));

        if ($title === '' || $url === '') {
            return null;
        }

        $published = $get('published_at', 'published_at');

        return new ParsedItem(
            title: $title,
            url: $url,
            summary: $this->nullable($get('summary', 'summary')),
            body: $this->nullable($get('body', 'body')),
            canonicalUrl: $this->nullable($get('canonical_url', 'canonical_url')),
            externalId: $this->nullable($get('external_id', 'id')),
            author: $this->nullable($get('author', 'author')),
            publishedAt: $this->date($published),
            payload: $row,
        );
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) (is_scalar($value) ? $value : ''));

        return $text === '' ? null : $text;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
