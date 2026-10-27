<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Enums\ArticleStatus;
use App\Events\ArticlePublished;
use App\Events\ArticleStatusChanged;
use App\Events\ArticleUnpublished;
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
        private readonly UpdateGateFailuresCount $gateCount,
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

        /*
         * Leaving fact_check is the moment the article counts as checked — it
         * is what stamps `fact_checked_at` and `fact_checker_id`, and what the
         * publish gate then asserts. So it is authorised as `factCheck`, not as
         * a generic transition.
         *
         * This was missing. `transition` returns true when the actor owns the
         * article, so a writer moved their own piece out of fact_check and the
         * system recorded them as its fact-checker — while the editorial
         * standards page told readers that was impossible. The policy method
         * implementing the rule existed and nothing called it; its own test
         * passed the whole time, which is precisely why nobody noticed.
         *
         * Enforced here rather than only in the policy: a policy answers a
         * question the UI asks, and any code path that does not ask still gets
         * through. The action is the path.
         */
        if ($from === ArticleStatus::FactCheck && $to !== ArticleStatus::FactCheck) {
            Gate::forUser($actor)->authorize('factCheck', $article);

            /*
             * And again, without the policy.
             *
             * The policy is the same rule and would already have refused — but
             * a policy is a question the UI asks, and a path that does not ask
             * gets through, which is exactly the failure being fixed. This is
             * the invariant itself: the person who wrote it is not the person
             * who checked it, whatever any permission says.
             */
            if ($this->wrote($article, $actor)) {
                throw new AuthorizationException(
                    'A writer cannot fact-check their own article.',
                );
            }
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

        // A transition can change the gate result — entering editor_review
        // stamps fact_checked_at, for one — so the denormalised count is
        // refreshed here rather than left to drift until the next save.
        ($this->gateCount)($article);

        ArticleStatusChanged::dispatch($article, $from, $to, $actor, $note);

        if ($to === ArticleStatus::Published) {
            ArticlePublished::dispatch($article, $actor);
        }

        /*
         * Leaving `published` is what invalidates every cached surface the
         * article sits on, so it is announced from here — the one path that
         * writes the column — rather than from an action a caller may or may
         * not choose.
         *
         * It was announced from `UnpublishArticle`, which nothing called. The
         * panel's «أرشفة» button goes straight through this class, so archiving
         * a live story fired no event, flushed no cache, and left it on the
         * homepage until the TTL expired. Measured before the fix: archived
         * through the real path, the story was still on `/ar` afterwards.
         */
        if ($from === ArticleStatus::Published && $to !== ArticleStatus::Published) {
            ArticleUnpublished::dispatch($article, $actor, $note);
        }

        return $article;
    }

    /**
     * Leaving fact_check for editor_review is the moment the article has
     * actually been checked — that is what the publish gate asserts, so it is
     * recorded here rather than trusted to whoever edits the field.
     */
    /**
     * Lead byline or credited co-author both count as having written it —
     * the same definition ArticlePolicy uses for ownership.
     */
    private function wrote(Article $article, User $actor): bool
    {
        if ((int) $article->author_id === (int) $actor->getKey()) {
            return true;
        }

        return $article->coAuthors()->whereKey($actor->getKey())->exists();
    }

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
