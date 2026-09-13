<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The single place an entity becomes a URL.
 *
 * Navigation, redirect validation, canonical tags, sitemaps, RSS and every
 * template all ask here. Before this existed, MenuTreeQuery built path strings
 * and ValidateRedirect built its own copy of the same shapes — so a redirect
 * could be validated against one spelling of a URL while the header rendered
 * another, and nobody would notice until a link broke in production.
 *
 * Nothing in this class concatenates a path. Everything goes through route(),
 * which means the route definitions are the only thing that can be wrong.
 */
class EntityUrl
{
    public function for(?Model $entity, ?string $locale = null, bool $absolute = false): ?string
    {
        if ($entity === null) {
            return null;
        }

        $locale ??= app()->getLocale();

        return match (true) {
            $entity instanceof Article => $this->article($entity, $locale, $absolute),
            $entity instanceof Category => route('web.category.show', [$locale, $entity->slug], $absolute),
            $entity instanceof Topic => route('web.topic.show', [$locale, $entity->slug], $absolute),
            $entity instanceof Company => route('web.company.show', [$locale, $entity->slug], $absolute),
            $entity instanceof Person => route('web.person.show', [$locale, $entity->slug], $absolute),
            $entity instanceof Opportunity => route('web.opportunity.show', [$locale, $entity->slug], $absolute),
            $entity instanceof User => route('web.author.show', [$locale, $entity->getKey()], $absolute),
            default => null,
        };
    }

    /**
     * An article lives under its category, so the category slug is part of its
     * identity. A missing category would produce a broken URL, so it is loaded
     * rather than assumed.
     */
    private function article(Article $article, string $locale, bool $absolute): ?string
    {
        $categorySlug = $article->relationLoaded('category')
            ? $article->category?->slug
            : Category::query()->whereKey($article->category_id)->value('slug');

        if ($categorySlug === null) {
            return null;
        }

        return route('web.article.show', [$locale, $categorySlug, $article->slug], $absolute);
    }

    /**
     * Resolve a morph alias plus slug to a URL, for callers that hold ids rather
     * than models — menu items, mostly.
     */
    public function forMorph(string $type, string $slug, ?string $locale = null, bool $absolute = false): ?string
    {
        $locale ??= app()->getLocale();

        return match ($type) {
            'category' => route('web.category.show', [$locale, $slug], $absolute),
            'topic' => route('web.topic.show', [$locale, $slug], $absolute),
            'company' => route('web.company.show', [$locale, $slug], $absolute),
            'person' => route('web.person.show', [$locale, $slug], $absolute),
            'opportunity' => route('web.opportunity.show', [$locale, $slug], $absolute),
            default => null,
        };
    }

    /**
     * Does this path correspond to something the public site actually serves?
     *
     * Used by redirect validation. Expressed as "build the URL for the content
     * and compare", not as a second set of path patterns — that duplication is
     * the bug this class exists to prevent.
     */
    public function pathServesLiveContent(string $path): bool
    {
        $path = '/'.trim($path, '/');
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if ($segments === []) {
            return false;
        }

        $locale = $segments[0];

        if (! array_key_exists($locale, (array) config('masar.locales'))) {
            return false;
        }

        // A locale root is always live.
        if (count($segments) === 1) {
            return true;
        }

        foreach ($this->candidatesFor($segments, $locale) as $candidate) {
            $url = $this->for($candidate, $locale);

            if ($url !== null && $this->pathOf($url) === $path) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every entity that could plausibly own this path. Comparison against the
     * generated URL is what decides.
     *
     * @param  array<int, string>  $segments
     * @return iterable<int, Model>
     */
    private function candidatesFor(array $segments, string $locale): iterable
    {
        $last = $segments[count($segments) - 1];

        if (count($segments) === 2) {
            $category = Category::query()->where('slug', $last)->where('is_active', true)->first();

            if ($category !== null) {
                yield $category;
            }

            return;
        }

        $section = $segments[1];

        $model = match ($section) {
            'topics' => Topic::query()->where('slug', $last)->first(),
            'companies' => Company::query()->where('slug', $last)->first(),
            'people' => Person::query()->where('slug', $last)->first(),
            'opportunities' => Opportunity::query()->where('locale', $locale)->where('slug', $last)->first(),
            default => Article::query()
                ->published()
                ->where('locale', $locale)
                ->where('slug', $last)
                ->with('category')
                ->first(),
        };

        if ($model !== null) {
            yield $model;
        }
    }

    private function pathOf(string $url): string
    {
        return '/'.trim((string) (parse_url($url, PHP_URL_PATH) ?: '/'), '/');
    }
}
