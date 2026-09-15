<?php

declare(strict_types=1);

namespace App\Actions\Newsletter;

use App\Enums\SubscriberStatus;
use App\Models\Subscriber;

/**
 * Turns a pending address into a subscriber.
 *
 * Idempotent on purpose: mail clients prefetch links, readers click twice, and
 * a confirmation that failed the second time would tell a reader their
 * subscription had not worked when it had.
 */
class ConfirmSubscription
{
    public function __invoke(Subscriber $subscriber): Subscriber
    {
        if ($subscriber->isConfirmed()) {
            return $subscriber;
        }

        $subscriber->forceFill([
            'status' => SubscriberStatus::Confirmed,
            'verified_at' => $subscriber->verified_at ?? now(),
            'unsubscribed_at' => null,
        ])->save();

        return $subscriber;
    }
}
