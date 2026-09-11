<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How significant an opportunity is judged to be.
 *
 * This is an editorial assessment made by a human, not a computed score. It
 * orders the opportunities listing, so inflating it devalues every other entry.
 */
enum OpportunityPotential: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'عالية',
            self::Medium => 'متوسطة',
            self::Low => 'منخفضة',
        };
    }

    /**
     * Sort weight, highest first. Stored as a string, so ordering in SQL would
     * otherwise be alphabetical (high, low, medium) — which is wrong.
     */
    public function weight(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
