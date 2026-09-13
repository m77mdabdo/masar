<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What we are allowed to keep from a source.
 *
 * Defaults to the restrictive mode, and the restrictive mode is the one that
 * needs no permission: a link, a headline, a publication date, and a summary we
 * wrote ourselves. `FullText` is opt-in per source, retains the publisher's body
 * for a fixed window, and that text is internal for its whole life — it never
 * renders publicly, never appears in a field an editor can copy from, and is
 * never exportable (CLAUDE.md §5).
 */
enum SourceLegalMode: string
{
    case Metadata = 'metadata';
    case FullText = 'full_text';

    public function label(): string
    {
        return match ($this) {
            self::Metadata => 'بيانات وصفية فقط',
            self::FullText => 'نص كامل (يُحذف بعد مدة الاحتفاظ)',
        };
    }

    /** Whether the publisher's own body text may be stored at all. */
    public function retainsBody(): bool
    {
        return $this === self::FullText;
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
