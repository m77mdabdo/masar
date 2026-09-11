<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticlePublished;
use App\Events\ArticleStatusChanged;

/**
 * Writes the workflow trail to the activity log.
 *
 * Deliberately synchronous: if the audit entry is lost, we cannot reconstruct
 * who moved a story and when, and that is exactly the question asked after
 * something goes wrong.
 */
class RecordStatusAudit
{
    public function handle(ArticleStatusChanged|ArticlePublished $event): void
    {
        if ($event instanceof ArticlePublished) {
            activity('article.published')
                ->performedOn($event->article)
                ->causedBy($event->actor)
                ->withProperties([
                    'published_at' => $event->article->published_at?->toIso8601String(),
                ])
                ->log('نُشرت المادة');

            return;
        }

        activity('article.status')
            ->performedOn($event->article)
            ->causedBy($event->actor)
            ->withProperties([
                'from' => $event->from->value,
                'to' => $event->to->value,
                'note' => $event->note,
            ])
            ->log(sprintf('%s ← %s', $event->to->label(), $event->from->label()));
    }
}
