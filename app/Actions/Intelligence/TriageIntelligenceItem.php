<?php

declare(strict_types=1);

namespace App\Actions\Intelligence;

use App\Enums\ReviewState;
use App\Models\IntelligenceItem;
use App\Models\User;
use RuntimeException;

/**
 * The only writer of an inbox item's review state.
 *
 * Triage is an editorial judgement, so it records who made it and when — a
 * dismissal with no name attached is indistinguishable from a bug, and the
 * 30-day recovery window is measured from `reviewed_at`. Both are set here so
 * no call site can set one without the other.
 */
class TriageIntelligenceItem
{
    public function __invoke(IntelligenceItem $item, ReviewState $state, User $actor): IntelligenceItem
    {
        if ($state === ReviewState::Drafted && $item->article_id === null) {
            // `Drafted` means an article exists. Setting it by hand would claim
            // a draft that nothing points at.
            throw new RuntimeException('لا يمكن وضع الحالة «أُنشئت مسودة» دون مقال مرتبط.');
        }

        $item->forceFill([
            'review_state' => $state,
            'reviewed_by_id' => $state === ReviewState::New ? null : $actor->getKey(),
            // Back to the queue means back to untriaged, including the clock
            // that the purge reads.
            'reviewed_at' => $state === ReviewState::New ? null : now(),
        ])->save();

        return $item;
    }
}
