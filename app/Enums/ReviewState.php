<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an item sits in the daily triage.
 *
 * The inbox is a queue an editor clears, not an archive they search, so every
 * state except `New` is a resolution. `Rejected` and `Ignored` stay recoverable
 * for the retention window and are then purged — an editor who dismisses the
 * wrong thing at 9am has the rest of the month to notice.
 */
enum ReviewState: string
{
    case New = 'new';
    case Saved = 'saved';
    case Approved = 'approved';
    case Drafted = 'drafted';
    case Rejected = 'rejected';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Saved => 'محفوظ لاحقًا',
            self::Approved => 'معتمد',
            self::Drafted => 'أُنشئت مسودة',
            self::Rejected => 'مرفوض',
            self::Ignored => 'متجاهَل',
        };
    }

    /** States that leave the queue. */
    public function isResolved(): bool
    {
        return $this !== self::New;
    }

    /**
     * Dismissals: kept for the retention window so a wrong call is reversible,
     * then purged.
     */
    public function isDismissal(): bool
    {
        return in_array($this, [self::Rejected, self::Ignored], true);
    }

    public function colour(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Saved => 'warning',
            self::Approved, self::Drafted => 'success',
            self::Rejected, self::Ignored => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $c, self $case): array => $c + [$case->value => $case->label()],
            [],
        );
    }
}
