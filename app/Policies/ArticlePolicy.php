<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('article.view');
    }

    public function view(User $user, Article $article): bool
    {
        return $user->can('article.view');
    }

    public function create(User $user): bool
    {
        return $user->can('article.create');
    }

    /**
     * A writer owns their article only while it is still theirs to write.
     * Anything at editor_review or beyond — and anything already published —
     * needs the broader permission.
     */
    public function update(User $user, Article $article): bool
    {
        if ($user->can('article.update.any')) {
            return true;
        }

        if (! $user->can('article.update.own') || ! $this->owns($user, $article)) {
            return false;
        }

        return $article->status->isBeforeEditorialReview();
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->can('article.delete');
    }

    /**
     * Moving an article along the workflow. Someone with only `own` rights can
     * hand off their own piece, but cannot push other people's work through.
     */
    public function transition(User $user, Article $article): bool
    {
        if (! $user->can('article.transition')) {
            return false;
        }

        if ($user->can('article.update.any')) {
            return true;
        }

        return $this->owns($user, $article);
    }

    /**
     * Publishing needs the permission *and* a clean gate. Checking the gate
     * here as well as in the Action is deliberate: it lets Filament disable the
     * button instead of letting an editor click it and read an exception.
     */
    public function publish(User $user, Article $article): bool
    {
        return $user->can('article.publish') && $article->isPublishable() === [];
    }

    /**
     * A writer cannot fact-check their own work. This is an editorial rule, not
     * a permission question — which is why it lives in code and not in a role.
     */
    public function factCheck(User $user, Article $article): bool
    {
        if (! $user->can('article.factcheck')) {
            return false;
        }

        return ! $this->owns($user, $article);
    }

    public function seo(User $user, Article $article): bool
    {
        return $user->can('article.seo');
    }

    /** Open, or re-run, a translation of this article in another edition. */
    public function translate(User $user, Article $article): bool
    {
        return $user->can('article.translate');
    }

    /**
     * Sign a translation off. The translator is not the reviewer, for the
     * reason a writer is not their own fact-checker.
     */
    public function reviewTranslation(User $user, Article $article): bool
    {
        return $user->can('article.translation.review') && ! $this->owns($user, $article);
    }

    /**
     * Lead byline or credited co-author both count as ownership.
     */
    private function owns(User $user, Article $article): bool
    {
        if ((int) $article->author_id === (int) $user->getKey()) {
            return true;
        }

        return $article->relationLoaded('coAuthors')
            ? $article->coAuthors->contains($user->getKey())
            : $article->coAuthors()->whereKey($user->getKey())->exists();
    }
}
