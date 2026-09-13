<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Fetchers;

use App\Models\Source;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only place this system talks to a publisher.
 *
 * Everything polite lives here so no fetcher can forget it: a User-Agent that
 * names us and gives someone a contact, conditional GET so a feed that has not
 * changed costs both sides nothing, and 429 / Retry-After honoured as an
 * instruction rather than an error.
 */
final class HttpClient
{
    /**
     * @return array{response: Response|null, error: string|null, retryAfter: int|null}
     */
    public function get(Source $source, string $url): array
    {
        $headers = ['User-Agent' => (string) config('masar.intelligence.user_agent')];

        // Conditional GET. Most polls should end here, with a 304.
        if (filled($source->etag)) {
            $headers['If-None-Match'] = $source->etag;
        }

        if (filled($source->last_modified)) {
            $headers['If-Modified-Since'] = $source->last_modified;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) config('masar.intelligence.timeout_seconds', 15))
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (ConnectionException $e) {
            return ['response' => null, 'error' => 'connection: '.$e->getMessage(), 'retryAfter' => null];
        }

        if ($response->status() === 429 || $response->status() === 503) {
            // An instruction, not a failure of ours. Honoured exactly.
            $retryAfter = $response->header('Retry-After');

            return [
                'response' => $response,
                'error' => 'rate limited ('.$response->status().')',
                'retryAfter' => is_numeric($retryAfter) ? (int) $retryAfter : null,
            ];
        }

        if ($response->failed()) {
            return ['response' => $response, 'error' => 'http '.$response->status(), 'retryAfter' => null];
        }

        return ['response' => $response, 'error' => null, 'retryAfter' => null];
    }
}
