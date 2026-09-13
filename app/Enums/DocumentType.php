<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of thing an item is.
 *
 * Not a topic — a *form*. A tender and an earnings release are both economic
 * news, and they reach a reader's decision by completely different routes, so
 * the form is what the importance score and the routing rules key on.
 */
enum DocumentType: string
{
    case Decision = 'decision';
    case Regulation = 'regulation';
    case Tender = 'tender';
    case Licence = 'licence';
    case Funding = 'funding';
    case Appointment = 'appointment';
    case Earnings = 'earnings';
    case Statistic = 'statistic';
    case Announcement = 'announcement';
    case Report = 'report';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Decision => 'قرار',
            self::Regulation => 'تنظيم',
            self::Tender => 'مناقصة',
            self::Licence => 'ترخيص',
            self::Funding => 'تمويل',
            self::Appointment => 'تعيين',
            self::Earnings => 'نتائج مالية',
            self::Statistic => 'إحصاء',
            self::Announcement => 'إعلان',
            self::Report => 'تقرير',
            self::Other => 'غير مصنّف',
        };
    }

    /**
     * How much this form of document moves the importance score, 0–1.
     *
     * A decision or a regulation changes what a business may do. An
     * announcement usually does not.
     */
    public function weight(): float
    {
        return match ($this) {
            self::Decision, self::Regulation => 1.0,
            self::Tender, self::Licence, self::Funding => 0.9,
            self::Earnings, self::Statistic => 0.7,
            self::Report, self::Appointment => 0.5,
            self::Announcement => 0.4,
            self::Other => 0.3,
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
