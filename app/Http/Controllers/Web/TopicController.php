<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Topic;
use App\Queries\PublishedArticlesQuery;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TopicController
{
    public function __invoke(string $locale, string $slug): View
    {
        $topic = Topic::query()->where('slug', $slug)->with('translations')->first();

        if ($topic === null) {
            throw new NotFoundHttpException;
        }

        return view('pages.topic', [
            'topic' => $topic,
            'articles' => PublishedArticlesQuery::make()
                ->forLocale($locale)->withTopic($topic)->latest()
                ->paginate((int) setting('editorial.articles_per_page')),
        ]);
    }
}
