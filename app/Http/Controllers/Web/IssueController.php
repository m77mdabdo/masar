<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Topic;
use App\Queries\PublishedArticlesQuery;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An "issue" is a featured topic — the magazine framing of records we already
 * have, so a themed package needs no second content type and no second editor
 * workflow.
 */
class IssueController
{
    public function __invoke(string $locale, string $slug): View
    {
        $issue = Topic::query()
            ->where('slug', $slug)
            ->where('is_featured', true)
            ->with('translations')
            ->first();

        if ($issue === null) {
            throw new NotFoundHttpException;
        }

        $articles = PublishedArticlesQuery::make()
            ->forLocale($locale)->withTopic($issue)->latest()->limit(24)->get();

        return view('pages.issue', [
            'issue' => $issue,
            'cover' => $articles->first(),
            'articles' => $articles->skip(1)->values(),
            // Chapters are the categories represented in the issue: the natural
            // division of a themed package without inventing a chapter entity.
            'chapters' => $articles->groupBy(fn ($article) => $article->category?->name ?? '—'),
        ]);
    }
}
