<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

/**
 * One URL, one form.
 *
 * Stage one of deduplication is an exact match, and an exact match is worthless
 * if the same article arrives as three URLs that differ only by a campaign
 * parameter. Normalising first is what makes the cheap stage do any work.
 */
final class UrlNormaliser
{
    public function __invoke(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);

        // `www.` is never semantically meaningful and is inconsistently used by
        // the same publisher across their own feed and their own pages.
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        // A default port is the same address written longer.
        $port = isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)
            ? ':'.$parts['port']
            : '';

        // A trailing slash on a path is the same document.
        $path = rtrim($parts['path'] ?? '', '/');

        return $scheme.'://'.$host.$port.$path.$this->query($parts['query'] ?? '');
    }

    /** A hash, because a URL is too long to index and we only ever compare. */
    public function hash(?string $url): ?string
    {
        $normalised = $this($url);

        return $normalised === null ? null : hash('sha256', $normalised);
    }

    /**
     * Tracking parameters removed, the rest sorted — two URLs that differ only
     * in parameter order are one URL.
     */
    private function query(string $query): string
    {
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);

        foreach ((array) config('masar.intelligence.strip_query_params', []) as $strip) {
            unset($params[$strip]);
        }

        if ($params === []) {
            return '';
        }

        ksort($params);

        return '?'.http_build_query($params);
    }
}
