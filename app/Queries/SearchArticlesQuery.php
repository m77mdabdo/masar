<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Article;
use App\Support\ArabicNormaliser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Article search with Arabic folding on both sides.
 *
 * Meilisearch is the target (Scout, per the locked decision), but the folding
 * has to happen here regardless: an index that stores raw Arabic cannot match
 * "استثمار" against "الاستثمار" no matter which engine is behind it.
 *
 * Until Scout is wired, this falls back to a LIKE query over a normalised
 * column-equivalent computed on the fly. That is honest for launch-scale content
 * and swaps for `Article::search()` without changing the caller.
 */
class SearchArticlesQuery
{
    public function __construct(
        private readonly ArabicNormaliser $normalise,
    ) {}

    public function __invoke(string $term, string $locale, int $perPage = 15): LengthAwarePaginator
    {
        $folded = ($this->normalise)($term);

        if ($folded === '') {
            return PublishedArticlesQuery::make()->forLocale($locale)->builder()->whereRaw('1 = 0')->paginate($perPage);
        }

        $words = array_slice(explode(' ', $folded), 0, 6);

        return PublishedArticlesQuery::make()
            ->forLocale($locale)
            ->latest()
            ->builder()
            ->where(function ($query) use ($words): void {
                foreach ($words as $word) {
                    $query->orWhere(function ($inner) use ($word): void {
                        // Matched against the same folding applied to the stored
                        // text, so prefixes and hamza variants line up.
                        $inner->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(title, "أ", "ا"), "إ", "ا"), "آ", "ا"), "ة", "ه") LIKE ?', ["%{$word}%"])
                            ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(body, "أ", "ا"), "إ", "ا"), "آ", "ا"), "ة", "ه") LIKE ?', ["%{$word}%"]);
                    });
                }
            })
            ->paginate($perPage)
            ->withQueryString();
    }
}
