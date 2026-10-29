<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Enums\TranslationReviewStatus;
use App\Models\TranslationReview;
use App\Support\Locales;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Brings the review register in line with the files on disk.
 *
 * Every interface translation file in every edition gets a row. A new file is
 * pending review. A file whose content changed since it was approved loses the
 * approval — the reviewer read different words — and records when. Nothing
 * here ever sets Approved: only ApproveTranslationFile does, for a named
 * person, against the hash they reviewed.
 */
final class SyncTranslationReviews
{
    /** @return list<TranslationReview> */
    public function __invoke(): array
    {
        $rows = [];

        foreach (Locales::codes() as $locale) {
            $files = array_merge(glob(lang_path("{$locale}/*.php")) ?: [], is_file(lang_path("{$locale}.json")) ? [lang_path("{$locale}.json")] : []);

            foreach ($files as $file) {
                $rows[] = $this->record($locale, $file);
            }
        }

        return $rows;
    }

    private function record(string $locale, string $file): TranslationReview
    {
        $path = str_replace(base_path().'/', '', $file);
        $hash = hash_file('sha256', $file);
        $total = $this->count($file);
        $source = $locale === Locales::default();

        $row = TranslationReview::query()->firstOrNew(['locale' => $locale, 'path' => $path]);
        $changed = $row->exists && $row->content_hash !== $hash;

        $row->fill([
            'content_hash' => $hash,
            'total_strings' => $total,
            'file_modified_at' => Carbon::createFromTimestamp(filemtime($file)),
        ]);

        if ($source) {
            $row->fill(['status' => TranslationReviewStatus::Source, 'reviewed_strings' => 0, 'pending_strings' => 0]);
        } elseif (! $row->exists || ($changed && $row->status === TranslationReviewStatus::Approved)) {
            $row->fill([
                'status' => TranslationReviewStatus::PendingReview,
                'reviewer_id' => null,
                'reviewed_at' => null,
                'invalidated_at' => $row->exists ? now() : null,
            ]);
        }

        // Owner publication approval is bound to the words it approved, like a
        // review: a changed file is no longer the file that was authorised.
        if ($row->publication_status === 'owner_approved' && $row->publication_hash !== $hash) {
            $row->fill(['publication_status' => 'unapproved', 'publication_approved_by' => null, 'publication_basis' => null,
                'publication_hash' => null, 'publication_approved_at' => null]);
        }

        if (! $source) {
            $approved = $row->status === TranslationReviewStatus::Approved;
            $row->fill(['reviewed_strings' => $approved ? $total : 0, 'pending_strings' => $approved ? 0 : $total]);
        }

        $row->save();

        return $row;
    }

    private function count(string $file): int
    {
        $data = str_ends_with($file, '.json') ? json_decode((string) file_get_contents($file), true) : require $file;

        return count(Arr::dot((array) $data));
    }
}
