<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Enums\TranslationStatus;
use App\Exceptions\TranslationNotAllowed;
use App\Models\Article;
use App\Models\User;
use App\Support\TranslatableContent;
use Illuminate\Support\Facades\Gate;

/**
 * A person signs a translation off against its source as it stands now.
 *
 * This is the only way `translation_status` becomes Reviewed, and Reviewed is
 * what the publish gate requires of any translation. Signing off also records
 * the source's current hash: the reviewer is asserting the text matches the
 * source today, so it stops reading as outdated until the source changes
 * again. From here no machine run overwrites it.
 */
final class MarkTranslationReviewed
{
    public function __construct(private readonly TranslatableContent $content) {}

    public function __invoke(Article $draft, User $actor): Article
    {
        Gate::forUser($actor)->authorize('reviewTranslation', $draft);

        if (! $draft->isTranslation() || $draft->source === null) {
            throw TranslationNotAllowed::notATranslation();
        }

        // Again without the policy: a path that does not ask still gets here.
        if ((int) $draft->author_id === (int) $actor->id) {
            throw TranslationNotAllowed::selfReview();
        }

        $draft->forceFill([
            'translation_status' => TranslationStatus::Reviewed,
            'translation_reviewed_by_id' => $actor->id,
            'translation_reviewed_at' => now(),
            'source_hash' => $this->content->hash($draft->source),
            'translation_error' => null,
        ])->save();

        activity('translation')->performedOn($draft)->causedBy($actor)->log('reviewed');

        return $draft;
    }
}
