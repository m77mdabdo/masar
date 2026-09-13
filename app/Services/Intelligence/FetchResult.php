<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

/**
 * What one poll produced.
 *
 * `NotModified` is a success, not a no-op: the source answered, conditional GET
 * worked, and the failure streak resets. Conflating it with a failure would
 * disable a perfectly healthy source that simply has not published today.
 */
final readonly class FetchResult
{
    /**
     * @param  array<int, ParsedItem>  $items
     */
    private function __construct(
        public bool $ok,
        public bool $notModified,
        public array $items = [],
        public ?string $etag = null,
        public ?string $lastModified = null,
        public ?string $error = null,
        public ?int $retryAfterSeconds = null,
    ) {}

    /** @param array<int, ParsedItem> $items */
    public static function ok(array $items, ?string $etag = null, ?string $lastModified = null): self
    {
        return new self(ok: true, notModified: false, items: $items, etag: $etag, lastModified: $lastModified);
    }

    public static function notModified(): self
    {
        return new self(ok: true, notModified: true);
    }

    public static function failed(string $error, ?int $retryAfterSeconds = null): self
    {
        return new self(ok: false, notModified: false, error: $error, retryAfterSeconds: $retryAfterSeconds);
    }
}
