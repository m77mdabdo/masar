<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Enums\TranslationStatus;
use App\Exceptions\TranslationNotAllowed;
use App\Jobs\TranslateArticle;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Queues a translation draft to be translated again — after a failure, or when
 * its source has moved on. Refuses a reviewed one: a person's sign-off is not
 * something a button discards.
 */
final class RetryTranslation
{
    public function __invoke(Article $draft, User $actor): Article
    {
        Gate::forUser($actor)->authorize('translate', $draft);

        if (! $draft->isTranslation()) {
            throw TranslationNotAllowed::notATranslation();
        }

        if ($draft->translation_status?->isProtected()) {
            throw TranslationNotAllowed::protected();
        }

        if (! ($draft->translation_status?->canRetry() ?? true)) {
            throw TranslationNotAllowed::notRetryable();
        }

        $draft->forceFill(['translation_status' => TranslationStatus::Queued, 'translation_error' => null])->save();
        TranslateArticle::dispatch($draft->id);

        return $draft;
    }
}
