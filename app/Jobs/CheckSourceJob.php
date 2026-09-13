<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Intelligence\RecordSourceResult;
use App\Actions\Intelligence\StoreSourceItem;
use App\Enums\SourceType;
use App\Models\Source;
use App\Services\Intelligence\Fetchers\ApiFetcher;
use App\Services\Intelligence\Fetchers\Fetcher;
use App\Services\Intelligence\Fetchers\RssFetcher;
use App\Services\Intelligence\Fetchers\SitemapFetcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * One source, one poll.
 *
 * Per-source rather than per-batch so a slow publisher cannot hold up the rest,
 * and `WithoutOverlapping` so a source that takes longer than its own interval
 * never has a second copy start behind it and ingest everything twice.
 */
class CheckSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $sourceId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->sourceId))->dontRelease()];
    }

    public function handle(
        StoreSourceItem $store,
        RecordSourceResult $record,
    ): void {
        $source = Source::query()->find($this->sourceId);

        if ($source === null || ! $source->is_active) {
            return;
        }

        $result = $this->fetcher($source->type)->fetch($source);

        $newItems = 0;

        if ($result->ok && ! $result->notModified) {
            foreach ($result->items as $parsed) {
                if ($store($source, $parsed) !== null) {
                    $newItems++;
                }
            }
        }

        // Health is written once, at the end, whatever happened — including the
        // path where the fetch succeeded and produced nothing.
        $record($source, $result, $newItems);
    }

    private function fetcher(SourceType $type): Fetcher
    {
        return match ($type) {
            SourceType::Rss, SourceType::Atom => app(RssFetcher::class),
            SourceType::Api => app(ApiFetcher::class),
            SourceType::Sitemap => app(SitemapFetcher::class),
        };
    }
}
