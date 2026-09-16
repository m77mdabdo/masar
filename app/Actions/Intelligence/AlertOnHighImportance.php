<?php

declare(strict_types=1);

namespace App\Actions\Intelligence;

use App\Models\IntelligenceItem;
use App\Models\User;
use App\Notifications\HighImportanceItem;
use Illuminate\Support\Facades\Notification;

/**
 * Raises an alert for an inbox item that scores above the threshold.
 *
 * Three refusals, each of which is a way this becomes noise:
 *
 * - **A duplicate never alerts.** The story already arrived; telling someone
 *   twice about one event is the fastest way to get the channel muted.
 * - **Nothing alerts when the threshold is null.** Turning it off has to be
 *   possible without deleting code.
 * - **Only people who can act.** A notification nobody can act on is the silent
 *   failure with extra steps.
 */
class AlertOnHighImportance
{
    public function __invoke(IntelligenceItem $item): bool
    {
        $threshold = config('masar.intelligence.alert_importance');

        if ($threshold === null) {
            return false;
        }

        if ($item->duplicate_of_id !== null) {
            return false;
        }

        if ($item->liveImportance() < (int) $threshold) {
            return false;
        }

        $recipients = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super_admin', 'editor_in_chief', 'editor']))
            ->get();

        if ($recipients->isEmpty()) {
            return false;
        }

        Notification::send($recipients, new HighImportanceItem($item));

        return true;
    }
}
