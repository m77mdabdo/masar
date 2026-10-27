<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A URL we did not build, about to be put in an `href` or a `Location` header.
 *
 * Every such URL in this codebase is validated where it is entered — Filament's
 * `->url()` rejects `javascript:` and `data:`, measured. That is the entry
 * point we have today, and it is not the only one a row can arrive through: a
 * seeder, an import, a fixture, a hand-edited column. A rule enforced where the
 * data is typed is not enforced where the data is drawn (CLAUDE.md §7), and the
 * cost of being wrong here is a link that runs script when a reader clicks it.
 *
 * Anything that is not http or https comes back null, and the caller renders
 * plain text instead of a link.
 */
final class SafeUrl
{
    /**
     * @return string|null  the URL if it is safe to link to, otherwise null
     */
    public static function external(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return null;
        }

        return in_array(strtolower($scheme), ['http', 'https'], true) ? $url : null;
    }
}
