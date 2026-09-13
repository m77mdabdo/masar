<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Topic;
use App\Queries\SearchArticlesQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController
{
    public function __invoke(Request $request, string $locale, SearchArticlesQuery $search): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('pages.search', [
            'term' => $term,
            'results' => $term === '' ? null : $search($term, $locale),
            // A dead end helps nobody: an empty result set offers somewhere to go.
            'suggestions' => Topic::query()
                ->where('articles_count', '>', 0)
                ->with('translations')
                ->orderByDesc('articles_count')
                ->take(8)
                ->get(),
        ]);
    }
}
