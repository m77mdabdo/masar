<?php

declare(strict_types=1);

namespace App\Actions\Newsletter;

use App\Enums\SubscriberStatus;
use App\Models\Subscriber;
use App\Notifications\ConfirmNewsletterSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Takes an address someone typed and, if it is new to us, asks them to prove
 * they own it.
 *
 * The one rule that shapes everything here: **the caller learns nothing about
 * the address.** Whether it is brand new, already pending, already confirmed or
 * previously unsubscribed, this returns the same value and the page says the
 * same sentence. A form that answers "you are already subscribed" is an
 * address-enumeration oracle — anyone can then test whether a given person
 * reads MASAR, which is not ours to disclose.
 *
 * That is also why nothing here throws on a duplicate.
 */
class SubscribeToNewsletter
{
    public function __invoke(
        string $email,
        string $locale,
        ?string $visitorId = null,
        ?string $source = null,
    ): void {
        /** @var Subscriber $subscriber */
        [$subscriber, $shouldSend] = DB::transaction(function () use ($email, $locale, $visitorId, $source) {
            // Locked for update: two submissions of the same address racing each
            // other would otherwise both pass the existence check and one would
            // die on the unique index.
            $subscriber = Subscriber::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($subscriber === null) {
                $subscriber = new Subscriber([
                    'email' => $email,
                    'locale' => $locale,
                    'status' => SubscriberStatus::Pending,
                    'visitor_id' => $visitorId,
                    'source' => $source,
                ]);
                $subscriber->save();

                return [$subscriber, true];
            }

            // Already a confirmed reader: nothing to do, and above all no second
            // confirmation mail. Re-submitting must not be a way to mail them.
            if ($subscriber->isConfirmed()) {
                // Still worth knowing they came back on this device.
                $this->rememberVisitor($subscriber, $visitorId);

                return [$subscriber, false];
            }

            // Someone who left and is asking to come back. Returning them to
            // pending — not straight to confirmed — means the re-entry is proved
            // the same way the first one was.
            if ($subscriber->status === SubscriberStatus::Unsubscribed) {
                $subscriber->status = SubscriberStatus::Pending;
                $subscriber->unsubscribed_at = null;
                $subscriber->verified_at = null;
            }

            $subscriber->locale = $locale;
            $subscriber->source ??= $source;
            $this->rememberVisitor($subscriber, $visitorId);
            $subscriber->save();

            return [$subscriber, $subscriber->mayBeSentConfirmation()];
        });

        if (! $shouldSend) {
            return;
        }

        $subscriber->forceFill(['confirmation_sent_at' => now()])->save();
        $subscriber->notify(new ConfirmNewsletterSubscription);
    }

    /**
     * The visitor id is the return-audience handle. It is only ever filled in,
     * never overwritten with null by a request that arrived without a cookie.
     */
    private function rememberVisitor(Subscriber $subscriber, ?string $visitorId): void
    {
        if ($visitorId !== null && $subscriber->visitor_id === null) {
            $subscriber->visitor_id = $visitorId;
        }
    }
}
