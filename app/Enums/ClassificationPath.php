<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which route classified an item.
 *
 * Recorded on every item, and the reason is evidential: when we later argue
 * about whether the AI provider earns its cost per thousand, this column is the
 * argument. Without it that conversation is two opinions.
 */
enum ClassificationPath: string
{
    case Rules = 'rules';
    case Ai = 'ai';
    case Manual = 'manual';
    case Unclassified = 'unclassified';

    public function label(): string
    {
        return match ($this) {
            self::Rules => 'قواعد',
            self::Ai => 'ذكاء اصطناعي',
            self::Manual => 'يدوي',
            self::Unclassified => 'غير مصنّف',
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
