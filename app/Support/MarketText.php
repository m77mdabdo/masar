<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Editor-entered market wording (labels, the source line) in the page's
 * edition. The figures are entered once, in Arabic; `market.translations`
 * maps each Arabic phrase to its rendering per edition. A phrase with no
 * rendering is returned as entered, and <x-ui.bidi> marks it as Arabic — the
 * coverage command reports it rather than hiding it.
 */
final class MarketText
{
    public static function for(?string $text): string
    {
        $text = (string) $text;
        $map = (array) setting()->forLocale('market.translations', Locales::default());

        return (string) ($map[$text][app()->getLocale()] ?? $text);
    }
}
