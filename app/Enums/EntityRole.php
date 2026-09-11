<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How central an entity is to a piece of content. Drives ordering on entity
 * profile pages: a company that *is* the story outranks one mentioned in passing.
 */
enum EntityRole: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Mentioned = 'mentioned';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'رئيسي',
            self::Secondary => 'ثانوي',
            self::Mentioned => 'مذكور',
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
