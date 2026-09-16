<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Fetchers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks a publisher's robots.txt for permission before we fetch anything.
 *
 * This was documented as implemented and was not — the fetcher sent a polite
 * User-Agent and then ignored the one file whose entire purpose is to tell it
 * where not to go. A bot that identifies itself honestly and disregards
 * robots.txt is worse than an anonymous one: it is rude under its own name.
 *
 * Follows RFC 9309 on the parts that matter:
 *
 * - The most specific User-Agent group wins; ours by name, otherwise `*`.
 * - Longest matching path rule wins, and Allow beats Disallow on a tie.
 * - `Disallow:` with an empty value allows everything.
 * - 404 means no restrictions. A 5xx or an unreachable host means **refuse** —
 *   "unreachable" is not "permitted", and guessing in our own favour is how a
 *   crawler gets an IP range banned.
 */
final class RobotsPolicy
{
    /**
     * One hour.
     *
     * Six hours is a long time to keep fetching from a publisher who has just
     * told us to stop, and we are the guest in that relationship. An hour still
     * saves 23 of every 24 daily robots fetches and bounds the disrespect to
     * something defensible.
     */
    private const CACHE_MINUTES = 60;

    private const CACHE_PREFIX = 'intelligence:robots:';

    public function allows(string $url, ?string $userAgent = null): bool
    {
        $rules = $this->rulesFor($url, $userAgent);

        if ($rules === null) {
            return false; // Unreachable or refused: do not fetch.
        }

        $path = $this->pathOf($url);
        $best = ['length' => -1, 'allow' => true];

        foreach ($rules['rules'] as [$kind, $pattern]) {
            if (! $this->matches($pattern, $path)) {
                continue;
            }

            $length = strlen($pattern);

            // Longest match wins; Allow wins a tie, per RFC 9309.
            if ($length > $best['length'] || ($length === $best['length'] && $kind === 'allow')) {
                $best = ['length' => $length, 'allow' => $kind === 'allow'];
            }
        }

        return $best['allow'];
    }

    /** The publisher's own requested pace, if they state one. */
    public function crawlDelaySeconds(string $url, ?string $userAgent = null): ?int
    {
        return $this->rulesFor($url, $userAgent)['crawlDelay'] ?? null;
    }

    /**
     * @return array{rules: array<int, array{0: string, 1: string}>, crawlDelay: int|null}|null
     */
    private function rulesFor(string $url, ?string $userAgent): ?array
    {
        $agent = $userAgent ?? (string) config('masar.intelligence.user_agent');
        $token = $this->tokenOf($agent);
        $origin = $this->originOf($url);

        if ($origin === null) {
            return null;
        }

        $cached = Cache::remember(
            self::CACHE_PREFIX.md5($origin.'|'.$token),
            now()->addMinutes(self::CACHE_MINUTES),
            function () use ($origin, $agent, $token): array {
                try {
                    $response = Http::withHeaders(['User-Agent' => $agent])
                        ->timeout(10)
                        ->withOptions(['allow_redirects' => ['max' => 3]])
                        ->get($origin.'/robots.txt');
                } catch (ConnectionException|Throwable) {
                    return ['state' => 'unreachable'];
                }

                if ($response->status() === 404 || $response->status() === 410) {
                    return ['state' => 'open', 'rules' => [], 'crawlDelay' => null];
                }

                if ($response->serverError() || $response->status() === 429) {
                    return ['state' => 'unreachable'];
                }

                if (! $response->successful()) {
                    // 401/403 on robots.txt means the whole site is restricted.
                    return ['state' => 'closed'];
                }

                return ['state' => 'parsed'] + $this->parse($response->body(), $token);
            },
        );

        return match ($cached['state']) {
            'open', 'parsed' => ['rules' => $cached['rules'] ?? [], 'crawlDelay' => $cached['crawlDelay'] ?? null],
            default => null,
        };
    }

    /**
     * @return array{rules: array<int, array{0: string, 1: string}>, crawlDelay: int|null}
     */
    private function parse(string $body, string $token): array
    {
        $groups = [];
        $current = [];
        $startingGroup = true;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*/', '', $line) ?? '');

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                // Consecutive User-Agent lines share one group of rules.
                if (! $startingGroup) {
                    $current = [];
                    $startingGroup = true;
                }

                $groups[] = ['agent' => strtolower($value), 'rules' => &$current];

                continue;
            }

            if (in_array($field, ['allow', 'disallow', 'crawl-delay'], true)) {
                $startingGroup = false;
                $current[] = [$field, $value];
            }
        }
        unset($current);

        // Ours by name if present, otherwise the wildcard group.
        $chosen = null;
        foreach ($groups as $group) {
            if ($group['agent'] !== '*' && str_contains($token, $group['agent'])) {
                $chosen = $group['rules'];
                break;
            }
        }

        if ($chosen === null) {
            foreach ($groups as $group) {
                if ($group['agent'] === '*') {
                    $chosen = $group['rules'];
                    break;
                }
            }
        }

        $rules = [];
        $crawlDelay = null;

        foreach ($chosen ?? [] as [$field, $value]) {
            if ($field === 'crawl-delay') {
                $crawlDelay = is_numeric($value) ? (int) ceil((float) $value) : null;

                continue;
            }

            // `Disallow:` with nothing after it is an explicit allow-all.
            if ($field === 'disallow' && $value === '') {
                continue;
            }

            $rules[] = [$field === 'allow' ? 'allow' : 'disallow', $value];
        }

        return ['rules' => $rules, 'crawlDelay' => $crawlDelay];
    }

    /** Supports the `*` and `$` wildcards robots.txt allows. */
    private function matches(string $pattern, string $path): bool
    {
        if ($pattern === '') {
            return false;
        }

        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? substr($pattern, 0, -1) : $pattern;

        $quoted = array_map(
            static fn (string $part): string => preg_quote($part, '#'),
            explode('*', $pattern),
        );

        $regex = '#^'.implode('.*', $quoted).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, $path);
    }

    private function pathOf(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function originOf(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** The bare product token a robots.txt group would name, e.g. "masarbot". */
    private function tokenOf(string $userAgent): string
    {
        $token = strtolower(strtok($userAgent, '/ ') ?: $userAgent);

        return trim($token);
    }
}
