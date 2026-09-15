<?php

declare(strict_types=1);

namespace App\Actions\Newsletter;

use App\Enums\SubscriberStatus;
use App\Models\Subscriber;

/**
 * Takes a reader off the list.
 *
 * The row is kept rather than deleted, for one reason: a deleted address is one
 * a later import could re-add without anyone noticing it had opted out. The
 * record of having left is the thing that has to survive.
 *
 * Idempotent — a second unsubscribe is a no-op, not an error page for someone
 * who is already gone.
 */
class UnsubscribeFromNewsletter
{
    public function __invoke(Subscriber $subscriber): Subscriber
    {
        if ($subscriber->status === SubscriberStatus::Unsubscribed) {
            return $subscriber;
        }

        $subscriber->forceFill([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ])->save();

        return $subscriber;
    }
}
