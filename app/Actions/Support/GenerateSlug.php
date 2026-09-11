<?php

declare(strict_types=1);

namespace App\Actions\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Produces a slug that is unique within its locale.
 *
 * This never rewrites an existing slug. Changing the slug of a published
 * article is an editorial act that must also leave a redirect behind, and that
 * belongs to its own Action — silently mutating a live URL loses the ranking
 * that earned the reader.
 */
class GenerateSlug
{
    public function __construct(
        private readonly TransliterateArabic $transliterate,
    ) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __invoke(
        string $source,
        string $locale,
        string $modelClass,
        ?int $ignoreId = null,
        int $maxLength = 60,
    ): string {
        $base = $this->base($source, $modelClass, $maxLength);

        $candidate = $base;
        $suffix = 1;

        while ($this->taken($candidate, $locale, $modelClass, $ignoreId)) {
            $suffix++;
            $tail = '-'.$suffix;

            // Keep the total inside the limit by shortening the base, not the
            // suffix — a truncated suffix would collide all over again.
            $candidate = Str::limit($base, max(1, $maxLength - mb_strlen($tail)), '');
            $candidate = trim($candidate, '-').$tail;
        }

        return $candidate;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function base(string $source, string $modelClass, int $maxLength): string
    {
        $slug = ($this->transliterate)($source, $maxLength);

        if ($slug !== '') {
            return $slug;
        }

        // Nothing survived transliteration — an emoji-only or punctuation-only
        // headline. Fall back to the model name so the editor gets something
        // valid to edit rather than an exception.
        return Str::slug(class_basename($modelClass)) ?: 'item';
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function taken(string $slug, string $locale, string $modelClass, ?int $ignoreId): bool
    {
        $query = $modelClass::query()
            ->where('locale', $locale)
            ->where('slug', $slug);

        // Soft-deleted rows still occupy the (locale, slug) unique index, so a
        // slug that looks free would fail on insert if we ignored them.
        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
