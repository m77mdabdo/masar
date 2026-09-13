<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use Illuminate\Support\Carbon;

/**
 * One entry, as a fetcher understood it — before normalisation, dedup or
 * classification. A plain value object so a parser can be tested against a
 * fixture without a database.
 */
final readonly class ParsedItem
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $title,
        public string $url,
        public ?string $summary = null,
        public ?string $body = null,
        public ?string $canonicalUrl = null,
        public ?string $externalId = null,
        public ?string $author = null,
        public ?Carbon $publishedAt = null,
        public array $payload = [],
    ) {}
}
