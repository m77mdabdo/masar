<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Enums\ArticleStatus;
use App\Events\ArticleUnpublished;
use App\Models\Article;
use App\Models\User;
use InvalidArgumentException;

/**
 * Taking a story off the site. The reason is recorded because removing
 * published work is the kind of decision someone will ask about later.
 */
class UnpublishArticle
{
    public function __construct(
        private readonly TransitionArticleStatus $transition,
    ) {}

    public function __invoke(
        Article $article,
        User $actor,
        ArticleStatus $to = ArticleStatus::NeedsRevision,
        ?string $reason = null,
    ): Article {
        if (! in_array($to, [ArticleStatus::NeedsRevision, ArticleStatus::Archived], true)) {
            throw new InvalidArgumentException(
                'Unpublishing must target needs_revision or archived, got "'.$to->value.'".',
            );
        }

        $article = ($this->transition)($article, $to, $actor, $reason);

        ArticleUnpublished::dispatch($article, $actor, $reason);

        return $article;
    }
}
