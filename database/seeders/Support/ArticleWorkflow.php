<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use RuntimeException;

/**
 * Walks a seeded article along a *legal* path through the workflow.
 *
 * The seeder must not assign `status` directly — TransitionArticleStatus is the
 * only writer. Walking also means the demo data exercises the transition map for
 * real: if the map and the seeder ever disagree, seeding fails loudly instead of
 * producing states the application cannot actually reach.
 */
class ArticleWorkflow
{
    public function __construct(
        private readonly TransitionArticleStatus $transition,
    ) {}

    public function walkTo(Article $article, ArticleStatus $target, User $actor): Article
    {
        foreach ($this->pathBetween($article->status, $target) as $step) {
            $article = ($this->transition)($article, $step, $actor);
        }

        return $article;
    }

    /**
     * Shortest legal route between two statuses, breadth-first over the
     * transition map. `published` is never used as a stepping stone — reaching
     * it has side effects and its own gate.
     *
     * @return array<int, ArticleStatus>
     */
    public function pathBetween(ArticleStatus $from, ArticleStatus $to): array
    {
        if ($from === $to) {
            return [];
        }

        $queue = [[$from, []]];
        $seen = [$from->value => true];

        while ($queue !== []) {
            [$current, $path] = array_shift($queue);

            foreach ($current->allowedTransitions() as $next) {
                if (isset($seen[$next->value])) {
                    continue;
                }

                $nextPath = [...$path, $next];

                if ($next === $to) {
                    return $nextPath;
                }

                if ($next === ArticleStatus::Published) {
                    continue;
                }

                $seen[$next->value] = true;
                $queue[] = [$next, $nextPath];
            }
        }

        throw new RuntimeException("No legal path from {$from->value} to {$to->value}.");
    }
}
