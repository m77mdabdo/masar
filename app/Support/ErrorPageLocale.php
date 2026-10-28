<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The language an error page speaks.
 *
 * An error page usually renders before any route matched, so SetLocale never
 * ran and the application is still on its default. The URL already says which
 * edition the reader was in — `/en/…` — so that is what decides, whether or not
 * the edition is enabled yet: a reader who typed an English address should not
 * get an Arabic apology for it.
 */
final class ErrorPageLocale
{
    public static function apply(Request $request): string
    {
        $segment = (string) $request->segment(1);
        $locale = Locales::exists($segment) ? $segment : Locales::default();

        app()->setLocale($locale);

        return $locale;
    }

    public static function direction(string $locale): string
    {
        return Locales::dir($locale);
    }
}
