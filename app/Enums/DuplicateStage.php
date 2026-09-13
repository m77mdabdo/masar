<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of the four checks matched, cheapest first.
 *
 * The first three are certainties — the same URL, the same canonical URL, the
 * same publisher id. `Title` is a similarity judgement, and it is the reason
 * a match is *flagged* rather than discarded: a second development on the same
 * story is very often the actual news.
 */
enum DuplicateStage: string
{
    case Url = 'url';
    case CanonicalUrl = 'canonical_url';
    case ExternalId = 'external_id';
    case Title = 'title';

    public function label(): string
    {
        return match ($this) {
            self::Url => 'رابط مطابق',
            self::CanonicalUrl => 'رابط أساسي مطابق',
            self::ExternalId => 'معرّف الناشر مطابق',
            self::Title => 'عنوان متقارب',
        };
    }

    /** Whether the match is an identity, not a resemblance. */
    public function isCertain(): bool
    {
        return $this !== self::Title;
    }
}
