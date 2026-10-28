<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * A translation request the rules refuse — distinct from a permissions
 * failure, so the editor is told what is wrong rather than "forbidden".
 */
final class TranslationNotAllowed extends DomainException
{
    public static function sameLocale(): self
    {
        return new self(__('admin.translation.same_locale'));
    }

    public static function unknownLocale(string $locale): self
    {
        return new self(__('admin.translation.unknown_locale', ['locale' => $locale]));
    }

    public static function exists(string $locale): self
    {
        return new self(__('admin.translation.exists', ['locale' => \App\Support\Locales::name($locale)]));
    }

    public static function notATranslation(): self
    {
        return new self(__('admin.translation.not_a_translation'));
    }

    public static function protected(): self
    {
        return new self(__('admin.translation.protected'));
    }

    public static function selfReview(): self
    {
        return new self(__('admin.translation.self_review'));
    }

    public static function notRetryable(): self
    {
        return new self(__('admin.translation.not_retryable'));
    }
}
