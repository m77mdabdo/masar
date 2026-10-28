<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a translated article is in the translation pipeline. Not whether it is
 * published — that stays ArticleStatus, behind the publish gate, which refuses
 * any translation that is not Reviewed.
 *
 * "Not translated" is not a case: it is the absence of a row for the locale.
 * "Outdated" is not a case either: it is computed, by comparing the hash a
 * translation was made from with its source's hash today. Storing it would be
 * a second copy of a fact that can go stale.
 */
enum TranslationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    // The provider declined — today, always: the shipped provider is the null
    // one. The draft holds the source text for a person to translate.
    case AwaitingTranslator = 'awaiting_translator';
    case NeedsReview = 'needs_review';
    case Reviewed = 'reviewed';
    case Failed = 'failed';

    public function label(): string
    {
        return __('enums.translation_status.'.$this->value);
    }

    /** A person has signed it off; no machine run may overwrite it. */
    public function isProtected(): bool
    {
        return $this === self::Reviewed;
    }

    public function canRetry(): bool
    {
        return in_array($this, [self::Failed, self::AwaitingTranslator, self::NeedsReview], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(self::cases(), static fn (array $c, self $s): array => $c + [$s->value => $s->label()], []);
    }
}
