<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Translation\CreateTranslationDraft;
use App\Actions\Translation\RetryTranslation;
use App\Events\ArticlePublished;
use App\Models\Article;
use App\Support\Locales;
use Throwable;

/**
 * The two optional automations, both off by default
 * (`masar.translation.auto_on_publish`, `auto_retranslate`).
 *
 * They hang off publication, not creation: an article is the newsroom's until
 * it is public, and nothing unpublished is offered to a translation provider.
 *
 * A failure here is reported and swallowed. Publishing has already happened,
 * and a translation that could not be opened is not a reason to tell the
 * editor their publish failed.
 */
final class OpenTranslationDrafts
{
    public function __construct(
        private readonly CreateTranslationDraft $create,
        private readonly RetryTranslation $retry,
    ) {}

    public function handle(ArticlePublished $event): void
    {
        $source = $event->article;

        if ($source->isTranslation()) {
            return;
        }

        $existing = Article::query()
            ->where('translation_group_id', $source->translation_group_id)
            ->whereKeyNot($source->getKey())
            ->get();

        if (config('masar.translation.auto_on_publish') && $event->actor->can('translate', $source)) {
            foreach (Locales::enabled() as $locale) {
                if ($locale !== $source->locale && ! $existing->contains('locale', $locale)) {
                    $this->attempt(fn () => ($this->create)($source, $locale, $event->actor));
                }
            }
        }

        if (config('masar.translation.auto_retranslate')) {
            foreach ($existing as $draft) {
                // Reviewed ones are never re-run; they show as outdated.
                if ($draft->isTranslation() && ! $draft->translation_status?->isProtected() && $draft->translationIsOutdated()) {
                    $this->attempt(fn () => ($this->retry)($draft, $event->actor));
                }
            }
        }
    }

    private function attempt(callable $step): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
