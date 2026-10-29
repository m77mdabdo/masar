<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Article;
use Illuminate\Support\Facades\Route;

/**
 * The other editions of the page being shown, and only the ones that exist.
 *
 * One answer for three consumers — hreflang in <head>, the language switcher
 * and the sitemap — because an alternate that a crawler is told about and a
 * reader cannot reach (or the reverse) is the same defect seen from two sides.
 *
 * Fallback policy: a language with no version of this page is simply absent.
 * The switcher then offers that edition's front page, labelled as such; no
 * URL in one edition ever serves another edition's text.
 */
final class Alternates
{
    /**
     * A story's editions: itself plus every published translation, in enabled
     * editions only. A draft is not an alternate — its URL 404s.
     *
     * @return array<string, string> code => absolute URL
     */
    public static function forArticle(Article $article): array
    {
        $urls = app(EntityUrl::class);

        return Article::query()->published()
            ->where('translation_group_id', $article->translation_group_id)
            ->whereIn('locale', Locales::enabled())
            ->with('category')
            ->get()
            ->mapWithKeys(fn (Article $a): array => [$a->locale => $urls->for($a, $a->locale, true)])
            ->sortKeys()
            ->all();
    }

    /**
     * Any other page under the locale prefix: the same route in each enabled
     * edition. Structured pages (sections, topics, companies, the static
     * pages) exist in every edition; what they list is that edition's own.
     *
     * @return array<string, string>
     */
    public static function forCurrentRoute(): array
    {
        $route = Route::current();

        if ($route === null || $route->getName() === null || ! array_key_exists('locale', $route->parameters())) {
            return [];
        }

        // The reader's search and filters travel with them: switching the
        // language of a results page lands on the same results.
        $query = request()->getQueryString();
        $out = [];
        foreach (Locales::enabled() as $code) {
            $url = route($route->getName(), array_merge($route->parameters(), ['locale' => $code]), true);
            $out[$code] = $query ? $url.'?'.$query : $url;
        }

        return $out;
    }
}
