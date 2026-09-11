<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;

/**
 * Point-in-time snapshot, taken before every meaningful update.
 *
 * Stores the editorial substance only — not SEO fields, counters or timestamps,
 * which are either derived or uninteresting to restore.
 */
class CreateArticleRevision
{
    public function __invoke(Article $article, User $actor, ?string $note = null): ArticleRevision
    {
        return $article->revisions()->create([
            'user_id' => $actor->getKey(),
            'change_note' => $note,
            'snapshot' => $this->snapshot($article),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Article $article): array
    {
        return [
            'title' => $article->title,
            'subtitle' => $article->subtitle,
            'summary' => $article->summary,

            // The four fields that carry the Information → Understanding →
            // Opportunity journey. Losing these to a bad edit is the worst case.
            'why_it_matters' => $article->why_it_matters,
            'business_impact' => $article->business_impact,
            'opportunity' => $article->opportunity,
            'key_numbers' => $article->key_numbers,

            'blocks' => $article->blocks()
                ->orderBy('sort_order')
                ->get(['type', 'data', 'sort_order'])
                ->toArray(),

            'sources' => $article->sources()
                ->orderBy('sort_order')
                ->get(['title', 'url', 'publisher', 'source_type', 'accessed_at', 'sort_order'])
                ->toArray(),
        ];
    }
}
