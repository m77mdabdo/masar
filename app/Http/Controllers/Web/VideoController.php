<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Article;
use App\Queries\PublishedArticlesQuery;
use Illuminate\Contracts\View\View;

class VideoController
{
    public function index(string $locale): View
    {
        $videos = PublishedArticlesQuery::make()
            ->forLocale($locale)
            ->builder()
            ->whereHas('blocks', fn ($query) => $query->where('type', 'video'))
            ->with(['blocks' => fn ($query) => $query->where('type', 'video')])
            ->latest('published_at')
            ->paginate(12);

        return view('pages.video', [
            'featured' => $videos->first(),
            'videos' => $videos,
            // Topics double as series rails — a themed run of videos is a topic,
            // not a separate content type.
            'series' => Article::query()
                ->published()
                ->forLocale($locale)
                ->whereHas('blocks', fn ($query) => $query->where('type', 'video'))
                ->with('topics.translations')
                ->get()
                ->flatMap(fn (Article $article) => $article->topics)
                ->unique('id')
                ->take(8),
        ]);
    }
}
