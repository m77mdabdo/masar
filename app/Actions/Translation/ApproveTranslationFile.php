<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Enums\TranslationReviewStatus;
use App\Exceptions\TranslationNotAllowed;
use App\Models\TranslationReview;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Records that a named person reviewed one translation file.
 *
 * The only writer of Approved. It needs a real user with the permission, and
 * the file as it is on disk now must be the file the register last saw — a
 * person approves words they read, not whatever the file says tomorrow.
 */
final class ApproveTranslationFile
{
    public function __invoke(TranslationReview $review, User $reviewer): TranslationReview
    {
        Gate::forUser($reviewer)->authorize('translation.review');

        if ($review->status === TranslationReviewStatus::Source) {
            throw new TranslationNotAllowed(__('admin.translation.source_file'));
        }

        $file = base_path($review->path);
        if (! is_file($file) || hash_file('sha256', $file) !== $review->content_hash) {
            throw new TranslationNotAllowed(__('admin.translation.file_changed'));
        }

        $review->forceFill([
            'status' => TranslationReviewStatus::Approved,
            'reviewer_id' => $reviewer->id,
            'reviewed_at' => now(),
            'reviewed_strings' => $review->total_strings,
            'pending_strings' => 0,
        ])->save();

        return $review;
    }
}
