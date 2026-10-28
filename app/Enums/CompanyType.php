<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A startup is a company with `type = startup`, never a separate table.
 * Splitting them breaks the content graph and forces a painful migration later.
 */
enum CompanyType: string
{
    case Company = 'company';
    case Startup = 'startup';
    case Government = 'government';
    case Fund = 'fund';
    case Project = 'project';

    public function label(): string
    {
        return __('enums.company_type.'.$this->value);
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
