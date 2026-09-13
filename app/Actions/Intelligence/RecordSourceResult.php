<?php

declare(strict_types=1);

namespace App\Actions\Intelligence;

use App\Models\Source;
use App\Models\User;
use App\Notifications\SourceDisabled;
use App\Services\Intelligence\FetchResult;
use Illuminate\Support\Facades\Notification;

/**
 * The only writer of a source's health columns.
 *
 * Auto-disable lives here rather than in the job, because the rule is about the
 * source and not about one poll — and because a rule spread across two call
 * sites is a rule that will eventually disagree with itself.
 */
class RecordSourceResult
{
    public function __invoke(Source $source, FetchResult $result, int $newItems = 0): void
    {
        if ($result->ok) {
            $this->success($source, $result, $newItems);

            return;
        }

        $this->failure($source, $result);
    }

    private function success(Source $source, FetchResult $result, int $newItems): void
    {
        $attributes = [
            'last_checked_at' => now(),
            'last_success_at' => now(),
            // A 304 is a healthy answer. Treating it as a failure would disable
            // a working source that simply has not published today.
            'consecutive_failures' => 0,
            'error_message' => null,
        ];

        if (! $result->notModified) {
            $attributes['etag'] = $result->etag;
            $attributes['last_modified'] = $result->lastModified;
        }

        if ($newItems > 0) {
            $attributes['last_item_at'] = now();
        }

        $source->forceFill($attributes)->save();
    }

    private function failure(Source $source, FetchResult $result): void
    {
        $failures = $source->consecutive_failures + 1;
        $threshold = $source->failureThreshold();

        // Backoff by pushing the clock forward: doubling per failure, capped,
        // and a publisher's own Retry-After always wins over our arithmetic.
        $backoff = $result->retryAfterSeconds !== null
            ? (int) ceil($result->retryAfterSeconds / 60)
            : min(
                (int) config('masar.intelligence.max_backoff_minutes', 360),
                $source->poll_frequency_minutes * (2 ** min($failures, 6)),
            );

        $disabling = $failures >= $threshold && $source->is_active;

        $source->forceFill([
            'last_checked_at' => now()->addMinutes(max(0, $backoff - $source->poll_frequency_minutes)),
            'consecutive_failures' => $failures,
            'error_message' => $result->error,
            'is_active' => $disabling ? false : $source->is_active,
        ])->save();

        if ($disabling) {
            $this->notify($source->refresh());
        }
    }

    /**
     * Everyone who could act on it. A notification nobody receives is the
     * silent failure with extra steps.
     */
    private function notify(Source $source): void
    {
        $recipients = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super_admin', 'editor_in_chief']))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SourceDisabled($source));
    }
}
