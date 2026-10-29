<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\IssueVisitorId;
use App\Http\Middleware\SetLocale;

/**
 * Every cookie the public site sets, described from the code that sets it.
 *
 * The cookie policy page renders this list, and a test compares it with the
 * cookies a real response carries — so the page cannot list a cookie the site
 * does not set, or miss one it does. Names and lifetimes are read from their
 * sources, not restated.
 *
 * @phpstan-type Cookie array{name: string, purpose: string, kind: string, minutes: int}
 */
final class SiteCookies
{
    /** @return list<array{name: string, purpose: string, kind: string, minutes: int}> */
    public static function all(): array
    {
        $session = (int) config('session.lifetime');

        return [
            ['name' => (string) config('session.cookie'), 'purpose' => 'session', 'kind' => 'necessary', 'minutes' => $session],
            ['name' => 'XSRF-TOKEN', 'purpose' => 'xsrf', 'kind' => 'necessary', 'minutes' => $session],
            // cookie()->forever(): Laravel's five years.
            ['name' => SetLocale::COOKIE, 'purpose' => 'edition', 'kind' => 'preference', 'minutes' => 5 * 365 * 24 * 60],
            ['name' => IssueVisitorId::COOKIE, 'purpose' => 'visitor', 'kind' => 'measurement', 'minutes' => IssueVisitorId::LIFETIME_MINUTES],
        ];
    }

    /** A lifetime in the reader's language, in the largest whole unit. */
    public static function duration(int $minutes): string
    {
        return match (true) {
            $minutes >= 2 * 365 * 24 * 60 => __('pages.legal.cookies.years', ['years' => intdiv($minutes, 365 * 24 * 60)]),
            $minutes >= 2 * 24 * 60 => __('pages.legal.cookies.days', ['days' => intdiv($minutes, 24 * 60)]),
            default => __('pages.legal.cookies.minutes', ['minutes' => $minutes]),
        };
    }
}
