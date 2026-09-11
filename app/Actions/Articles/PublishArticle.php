<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Thin wrapper over TransitionArticleStatus. It owns the bookkeeping that only
 * matters at the moment of publication, and delegates the decision itself.
 */
class PublishArticle
{
    public function __construct(
        private readonly TransitionArticleStatus $transition,
    ) {}

    public function __invoke(Article $article, User $actor, ?string $note = null): Article
    {
        return DB::transaction(function () use ($article, $actor, $note): Article {
            // Written before the transition so the gate and the published row
            // see the same values, and so reading_time is never null on a live
            // article just because nobody opened the SEO tab.
            $article->published_at ??= now();
            $article->scheduled_for = null;

            // The accessor computes from the body when the column is empty;
            // assigning it back freezes that estimate onto the live row.
            $article->reading_time = $article->reading_time;

            $article->save();

            return ($this->transition)($article, ArticleStatus::Published, $actor, $note);
        });
    }
}
