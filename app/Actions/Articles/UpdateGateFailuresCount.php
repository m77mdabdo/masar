<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Models\Article;
use Illuminate\Support\Facades\DB;

/**
 * Persists the publish-gate result onto `articles.gate_failures_count`.
 *
 * Called at the write points where the gate's inputs can change — a save, a
 * transition — and never from a read. Evaluating on read would mean writing
 * during a GET, and `ArticlePolicy::publish()` calls the gate once per row when
 * a table renders, which would turn one listing into fifty updates.
 *
 * The write is a bare query, not a model save: it must not touch `updated_at`,
 * must not fire model events, and must not appear in the activity log. Nobody
 * wants an audit entry saying an editor changed a derived counter.
 */
class UpdateGateFailuresCount
{
    public function __construct(
        private readonly EvaluatePublishGate $gate,
    ) {}

    public function __invoke(Article $article): int
    {
        // The gate reads sources; a stale relation would produce a wrong count.
        $article->unsetRelation('sources');

        $count = count(($this->gate)($article));

        DB::table('articles')
            ->where('id', $article->getKey())
            ->update(['gate_failures_count' => $count]);

        // Keep the in-memory model consistent with what was just written,
        // without marking it dirty.
        $article->setAttribute('gate_failures_count', $count);
        $article->syncOriginalAttribute('gate_failures_count');

        return $count;
    }

    /**
     * Recompute for every article. Used by masar:recount.
     */
    public function all(): int
    {
        $touched = 0;

        Article::query()
            ->with('sources')
            ->chunkById(200, function ($articles) use (&$touched): void {
                foreach ($articles as $article) {
                    $this($article);
                    $touched++;
                }
            });

        return $touched;
    }
}
