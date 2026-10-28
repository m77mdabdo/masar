<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Enums\TranslationStatus;
use App\Exceptions\TranslationNotAllowed;
use App\Jobs\TranslateArticle;
use App\Models\Article;
use App\Models\User;
use App\Support\Locales;
use App\Support\TranslatableContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Opens a translation of an article in another edition, as a draft.
 *
 * The draft is a new article row in the source's translation group, created
 * at the default status — so it enters the ordinary workflow, is written to
 * only by TransitionArticleStatus from here on, and is published only through
 * the gate, which additionally requires a person to have reviewed it. The
 * source is never written to.
 *
 * Structure is copied (category, photograph, sources, topics, blocks, the
 * sponsorship and its disclosure); language starts as the source's own text
 * and is replaced by the translation job, or by a person when no provider
 * answers. The slug is the source's: slugs are unique per locale, and the
 * same slug in every edition is what lets the language switcher find the
 * same story.
 */
final class CreateTranslationDraft
{
    public function __construct(private readonly TranslatableContent $content) {}

    public function __invoke(Article $source, string $locale, User $actor): Article
    {
        Gate::forUser($actor)->authorize('translate', $source);

        if (! Locales::exists($locale)) {
            throw TranslationNotAllowed::unknownLocale($locale);
        }

        if ($locale === $source->locale) {
            throw TranslationNotAllowed::sameLocale();
        }

        $draft = DB::transaction(function () use ($source, $locale, $actor): Article {
            // Locked, so two clicks cannot both pass the check; the unique
            // index on (translation_group_id, locale) is the backstop.
            $exists = Article::withTrashed()
                ->where('translation_group_id', $source->translation_group_id)
                ->where('locale', $locale)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw TranslationNotAllowed::exists($locale);
            }

            $source->loadMissing(['blocks', 'sources', 'topics']);

            $draft = $source->replicate([
                'status', 'editor_id', 'fact_checker_id', 'scheduled_by_id', 'published_at', 'scheduled_for',
                'updated_content_at', 'fact_checked_at', 'views_count', 'gate_failures_count', 'canonical_url',
                'translation_status', 'translation_origin', 'source_hash', 'translation_error', 'translation_attempts',
                'translated_at', 'translation_reviewed_by_id', 'translation_reviewed_at', 'is_featured',
            ]);
            $draft->fill([
                'locale' => $locale,
                'author_id' => $actor->id,
                'source_article_id' => $source->id,
                'translation_status' => TranslationStatus::Queued,
                'source_hash' => $this->content->hash($source),
                'translation_attempts' => 0,
            ]);
            // A different story may already hold this slug in the target
            // edition; the source id keeps the new one unique and traceable.
            if (Article::withTrashed()->where('locale', $locale)->where('slug', $draft->slug)->exists()) {
                $draft->slug = $draft->slug.'-'.$source->id;
            }
            $draft->save();

            foreach ($source->blocks as $block) {
                $draft->blocks()->create($block->only(['type', 'section', 'data', 'sort_order']));
            }
            foreach ($source->sources as $row) {
                $draft->sources()->create($row->only(['title', 'url', 'publisher', 'source_type', 'accessed_at', 'sort_order']));
            }
            $draft->topics()->sync($source->topics->modelKeys());

            return $draft;
        });

        // Scalars only: the job must not carry a model it may outlive.
        TranslateArticle::dispatch($draft->id);

        return $draft;
    }
}
