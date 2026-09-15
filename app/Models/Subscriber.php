<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriberStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class Subscriber extends Model
{
    use HasFactory;
    use Notifiable;

    /** How long a confirmation link stays good. */
    public const CONFIRMATION_TTL_DAYS = 7;

    /** How long before the same address may be sent another confirmation. */
    public const RESEND_AFTER_MINUTES = 15;

    protected $fillable = [
        'email',
        'locale',
        'status',
        'verified_at',
        'confirmation_sent_at',
        'preferences',
        'visitor_id',
        'source',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'preferences' => 'array',
            'verified_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * The visitor id is the return-audience handle, not something to hand out.
     * It is hidden so it cannot ride along in a serialised response.
     */
    protected $hidden = [
        'visitor_id',
    ];

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', SubscriberStatus::Confirmed)
            ->whereNotNull('verified_at')
            ->whereNull('unsubscribed_at');
    }

    public function isConfirmed(): bool
    {
        return $this->status === SubscriberStatus::Confirmed
            && $this->verified_at !== null
            && $this->unsubscribed_at === null;
    }

    /**
     * Whether another confirmation mail may be sent to this address right now.
     *
     * The window is what stops the form being used to post mail to a stranger:
     * the submitter is shown the same neutral response either way, so there is
     * nothing to learn from being refused.
     */
    public function mayBeSentConfirmation(?Carbon $now = null): bool
    {
        if ($this->confirmation_sent_at === null) {
            return true;
        }

        return $this->confirmation_sent_at->lte(
            ($now ?? now())->subMinutes(self::RESEND_AFTER_MINUTES)
        );
    }

    /**
     * Signed, so the link proves we issued it and nobody can confirm an address
     * they do not control by guessing an id. Expiring, because an opt-in that
     * sat in an inbox for a year is not consent anyone still remembers giving.
     */
    public function confirmationUrl(): string
    {
        return URL::temporarySignedRoute(
            'web.newsletter.confirm',
            now()->addDays(self::CONFIRMATION_TTL_DAYS),
            ['locale' => $this->locale, 'subscriber' => $this->getKey()],
        );
    }

    /**
     * Never expires. An unsubscribe link that has gone stale is a reader who
     * cannot leave, which is the one failure this link exists to prevent.
     */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute(
            'web.newsletter.unsubscribe',
            ['locale' => $this->locale, 'subscriber' => $this->getKey()],
        );
    }
}
