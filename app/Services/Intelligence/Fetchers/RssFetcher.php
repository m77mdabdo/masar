<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Fetchers;

use App\Models\Source;
use App\Services\Intelligence\FetchResult;
use App\Services\Intelligence\ParsedItem;
use Illuminate\Support\Carbon;
use SimpleXMLElement;

/**
 * RSS 2.0 and Atom. One class because the two differ in element names and in
 * nothing that matters here — splitting them would duplicate the hard part
 * (dates, links, ids) to avoid duplicating the easy part.
 */
final class RssFetcher implements Fetcher
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

        $xml = $this->parseXml($response->body());

        if ($xml === null) {
            return FetchResult::failed('invalid xml');
        }

        $entries = $xml->channel->item ?? $xml->entry ?? null;

        if ($entries === null) {
            return FetchResult::failed('no items found in feed');
        }

        $items = [];
        $limit = (int) config('masar.intelligence.max_items_per_poll', 50);

        foreach ($entries as $entry) {
            if (count($items) >= $limit) {
                break;
            }

            $parsed = $this->item($entry);

            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return FetchResult::ok($items, $response->header('ETag') ?: null, $response->header('Last-Modified') ?: null);
    }

    private function parseXml(string $body): ?SimpleXMLElement
    {
        // A malformed feed is a source problem, not an exception for us: it is
        // recorded as a failure and counts toward auto-disable.
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body);

            return $xml === false ? null : $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function item(SimpleXMLElement $entry): ?ParsedItem
    {
        $title = trim((string) ($entry->title ?? ''));
        $url = $this->link($entry);

        // An entry with no title or no link is not something an editor can act
        // on, and storing it would put an empty row in the inbox every poll.
        if ($title === '' || $url === null) {
            return null;
        }

        return new ParsedItem(
            title: $title,
            url: $url,
            summary: $this->text($entry->description ?? $entry->summary ?? null),
            body: $this->body($entry),
            canonicalUrl: null,
            externalId: $this->externalId($entry),
            author: $this->author($entry),
            publishedAt: $this->date($entry),
            payload: ['raw' => $entry->asXML() ?: ''],
        );
    }

    private function link(SimpleXMLElement $entry): ?string
    {
        $link = trim((string) ($entry->link ?? ''));

        if ($link !== '') {
            return $link;
        }

        // Atom puts the URL in an attribute, and often ships several links with
        // different rel values. `alternate` is the human-readable one.
        foreach ($entry->link ?? [] as $candidate) {
            $rel = (string) ($candidate['rel'] ?? 'alternate');

            if ($rel === 'alternate' && filled((string) ($candidate['href'] ?? ''))) {
                return (string) $candidate['href'];
            }
        }

        return null;
    }

    private function externalId(SimpleXMLElement $entry): ?string
    {
        $id = trim((string) ($entry->guid ?? $entry->id ?? ''));

        return $id === '' ? null : $id;
    }

    private function author(SimpleXMLElement $entry): ?string
    {
        $author = trim((string) ($entry->author->name ?? $entry->author ?? ''));

        return $author === '' ? null : $author;
    }

    private function body(SimpleXMLElement $entry): ?string
    {
        // content:encoded, where a feed ships the whole article. Retained only
        // if the source's legal_mode allows it — enforced at the store, not
        // here, so a fetcher never has to know the rules.
        $content = $entry->children('content', true);

        return $this->text($content->encoded ?? $entry->content ?? null);
    }

    private function text(mixed $value): ?string
    {
        $text = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '' ? null : $text;
    }

    private function date(SimpleXMLElement $entry): ?Carbon
    {
        $raw = trim((string) ($entry->pubDate ?? $entry->published ?? $entry->updated ?? ''));

        if ($raw === '') {
            return null;
        }

        try {
            // Everything is stored in UTC. A feed's local offset is information
            // about the publisher, not about when the thing happened.
            return Carbon::parse($raw)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
