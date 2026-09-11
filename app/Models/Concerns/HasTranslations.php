<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Structured data (categories, topics, companies, countries…) keeps one row and
 * many translation rows, so the entity stays a single node in the content graph.
 *
 * Editorial content does NOT use this — articles are one row per locale linked
 * by translation_group_id, because an English story is a different story.
 *
 * The consuming model must define `protected string $translationForeignKey` only
 * when it differs from the conventional `<model>_id` (Person → person_id).
 */
trait HasTranslations
{
    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModel(), $this->translationForeignKey());
    }

    /**
     * Translation for a locale, falling back to the configured default and then
     * to whatever exists — a half-translated entity should still render a name.
     */
    public function translation(?string $locale = null): ?object
    {
        $locale ??= app()->getLocale();
        $fallback = config('masar.default_locale', 'ar');

        // Relies on the caller having eager-loaded `translations`; filtering in
        // memory here is what keeps entity listings off the N+1 path.
        $all = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $all->firstWhere('locale', $locale)
            ?? $all->firstWhere('locale', $fallback)
            ?? $all->first();
    }

    public function translate(string $attribute, ?string $locale = null): ?string
    {
        return $this->translation($locale)?->{$attribute};
    }

    public function getNameAttribute(): ?string
    {
        return $this->translate('name');
    }

    protected function translationModel(): string
    {
        return static::class.'Translation';
    }

    protected function translationForeignKey(): string
    {
        return property_exists($this, 'translationForeignKey')
            ? $this->translationForeignKey
            : str(class_basename(static::class))->snake()->toString().'_id';
    }
}
