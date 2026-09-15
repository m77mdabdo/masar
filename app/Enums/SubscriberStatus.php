<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a subscriber sits in the double opt-in cycle.
 *
 * `pending` is not a subscriber. It is an address someone typed, which nobody
 * has yet proved they control — the whole point of double opt-in is that the
 * two states are different, so nothing that sends mail may treat them alike.
 */
enum SubscriberStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار التأكيد',
            self::Confirmed => 'مؤكَّد',
            self::Unsubscribed => 'ألغى الاشتراك',
        };
    }

    /** Only a confirmed address may ever be sent an issue. */
    public function mayReceiveMail(): bool
    {
        return $this === self::Confirmed;
    }
}
