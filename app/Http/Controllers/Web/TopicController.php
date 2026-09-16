<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Topic;
use App\Queries\EntityProfileQuery;
use App\Queries\PublishedArticlesQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TopicController
{
    public function __invoke(string $locale, string $slug): View
    {
        $topic = Topic::query()->where('slug', $slug)->with('translations')->first();

        if ($topic === null) {
            throw new NotFoundHttpException;
        }

        // Who turns up in this topic's coverage. A topic is not a mentionable
        // entity — it links through `article_topic` — so the question runs the
        // other way round: given these articles, which entities appear in them.
        $articleIds = DB::table('article_topic')
            ->where('topic_id', $topic->getKey())
            ->select('article_id');

        return view('pages.topic', [
            'topic' => $topic,
            'entities' => EntityProfileQuery::entitiesInArticles($articleIds, perType: 5),
            'articles' => PublishedArticlesQuery::make()
                ->forLocale($locale)->withTopic($topic)->latest()
                ->paginate((int) setting('editorial.articles_per_page')),
        ]);
    }
}
