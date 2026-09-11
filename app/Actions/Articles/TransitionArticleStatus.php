<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Enums\ArticleStatus;
use App\Events\ArticlePublished;
use App\Events\ArticleStatusChanged;
use App\Exceptions\InvalidStatusTransition;
use App\Exceptions\PublishGateFailed;
use App\Models\Article;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The only place `articles.status` is ever written.
 *
 * Not a model mutator, not a Filament action, not a seeder. Every status change
 * is authorised, validated against the transition map, gated when it reaches
 * `published`, audited and announced — and a path that bypasses this class
 * bypasses all five.
 */
class TransitionArticleStatus
{
    public function __construct(
        private readonly EvaluatePublishGate $gate,
    ) {}

    /**
     * @throws InvalidStatusTransition
     * @throws PublishGateFailed
     */
    public function __invoke(
        Article $article,
        ArticleStatus $to,
        User $actor,
        ?string $note = null,
    ): Article {
        Gate::forUser($actor)->authorize('transition', $article);

        $from = $article->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransition::between($from, $to);
        }

        if ($to === ArticleStatus::Published) {
            // The permission and the gate are checked separately on purpose.
            // ArticlePolicy::publish() folds both together so Filament can
            // enable or disable one button, but here they are different
            // failures: "you may not publish" and "this is not ready" need
            // different answers, and collapsing them would tell an editor their
            // incomplete article is a permissions problem.
            if ($actor->cannot('article.publish')) {
                throw new AuthorizationException(
                    'You do not have permission to publish articles.',
                );
            }

            $failures = ($this->gate)($article);

            if ($failures !== []) {
                throw new PublishGateFailed($failures);
            }
        }

        $article = DB::transaction(function () use ($article, $from, $to, $actor): Article {
            $this->stampStageTimestamps($article, $from, $to, $actor);

            $article->status = $to;
            $article->save();

            return $article;
        });

        ArticleStatusChanged::dispatch($article, $from, $to, $actor, $note);

        if ($to === ArticleStatus::Published) {
            ArticlePublished::dispatch($article, $actor);
        }

        return $article;
    }

    /**
     * Leaving fact_check for editor_review is the moment the article has
     * actually been checked — that is what the publish gate asserts, so it is
     * recorded here rather than trusted to whoever edits the field.
     */
    private function stampStageTimestamps(
        Article $article,
        ArticleStatus $from,
        ArticleStatus $to,
        User $actor,
    ): void {
        if ($from === ArticleStatus::FactCheck && $to === ArticleStatus::EditorReview) {
            $article->fact_checked_at = now();
            $article->fact_checker_id ??= $actor->getKey();
        }
    }
}
