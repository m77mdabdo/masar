<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentType: string
{
    case News = 'news';
    case Analysis = 'analysis';
    case Explainer = 'explainer';
    case Interview = 'interview';
    case Opinion = 'opinion';
    case Story = 'story';
    case Report = 'report';
    case Sponsored = 'sponsored';

    public function label(): string
    {
        return match ($this) {
            self::News => 'خبر',
            self::Analysis => 'تحليل',
            self::Explainer => 'شرح',
            self::Interview => 'مقابلة',
            self::Opinion => 'رأي',
            self::Story => 'قصة',
            self::Report => 'تقرير',
            self::Sponsored => 'محتوى مدفوع',
        };
    }

    /**
     * Paid placement must be labelled to the reader, every time, without exception.
     */
    public function requiresDisclosure(): bool
    {
        return $this === self::Sponsored;
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
