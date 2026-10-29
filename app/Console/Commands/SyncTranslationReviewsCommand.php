<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Translation\SyncTranslationReviews;
use Illuminate\Console\Command;

/** Updates the interface-translation review register from the files on disk. */
class SyncTranslationReviewsCommand extends Command
{
    protected $signature = 'masar:translations:sync';

    protected $description = 'Record every interface translation file, its hash and its review status';

    public function handle(SyncTranslationReviews $sync): int
    {
        $rows = $sync();

        $this->table(['locale', 'file', 'strings', 'reviewed', 'pending', 'status', 'reviewer'], array_map(fn ($r): array => [
            $r->locale, $r->path, $r->total_strings, $r->reviewed_strings, $r->pending_strings, $r->status->value, $r->reviewer?->email ?? '—',
        ], $rows));

        return self::SUCCESS;
    }
}
