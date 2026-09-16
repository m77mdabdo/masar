<?php

declare(strict_types=1);

namespace App\Actions\Intelligence;

use App\Models\IntelligenceItem;
use App\Models\User;

/**
 * An editor saying "these are two stories, not one".
 *
 * This exists because the title stage is known-weak and says so: SimHash over a
 * six-token Arabic headline scored two different companies' results at 7 and a
 * genuine rewrite at 20, so wrong flags are expected rather than exceptional.
 * The design answer is to flag rather than discard and to make clearing a flag
 * one action — which is only true if the editor can also see what the item was
 * flagged against.
 *
 * The judgement is recorded on the item, not thrown away: `reviewed_by_id` and
 * `reviewed_at` carry who decided, the same as any other triage call.
 */
class ClearDuplicateFlag
{
    public function __invoke(IntelligenceItem $item, User $actor): IntelligenceItem
    {
        if ($item->duplicate_of_id === null) {
            return $item;
        }

        $item->forceFill([
            'duplicate_of_id' => null,
            'duplicate_stage' => null,
            'reviewed_by_id' => $actor->getKey(),
            'reviewed_at' => now(),
        ])->save();

        return $item;
    }
}
