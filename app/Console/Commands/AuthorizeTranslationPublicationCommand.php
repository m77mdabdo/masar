<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Translation\SyncTranslationReviews;
use App\Models\TranslationReview;
use App\Support\Locales;
use Illuminate\Console\Command;

/**
 * Records the owner's authorisation to publish an edition's interface text.
 *
 * Not a linguistic review: it never touches `status`, `reviewer_id` or
 * `reviewed_strings`. It records who authorised publication, on what basis
 * (both required, written as given), when, and the content hash of each file
 * as it stood — so a later change to a file voids that file's authorisation.
 */
class AuthorizeTranslationPublicationCommand extends Command
{
    protected $signature = 'masar:translations:authorize-publication
        {locales* : edition codes, or "all"}
        {--by= : who authorised publication (name or email, as they gave it)}
        {--basis= : where the authorisation was given}';

    protected $description = 'Record owner approval to publish interface translations (not a linguistic review)';

    public function handle(SyncTranslationReviews $sync): int
    {
        $by = trim((string) $this->option('by'));
        $basis = trim((string) $this->option('basis'));

        if ($by === '' || $basis === '') {
            $this->error('Both --by and --basis are required: an authorisation with no author is not one.');

            return self::FAILURE;
        }

        $locales = in_array('all', $this->argument('locales'), true) ? Locales::codes() : $this->argument('locales');
        if (($unknown = array_diff($locales, Locales::codes())) !== []) {
            $this->error('Unknown edition: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $sync();

        $rows = TranslationReview::query()->whereIn('locale', $locales)->get();
        foreach ($rows as $row) {
            $row->forceFill([
                'publication_status' => 'owner_approved',
                'publication_approved_by' => $by,
                'publication_basis' => $basis,
                'publication_hash' => $row->content_hash,
                'publication_approved_at' => now(),
            ])->save();
        }

        $this->info("Publication authorised for {$rows->count()} files in ".implode(', ', $locales).'. Linguistic review status is unchanged.');

        return self::SUCCESS;
    }
}
