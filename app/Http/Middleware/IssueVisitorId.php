<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A first-party anonymous identifier for the returning-audience measure.
 *
 * Returning audience is the primary KPI and reader accounts do not exist, so
 * the only durable handle we have on "the same person came back" is a cookie we
 * set ourselves. Three properties are deliberate:
 *
 * - It is opaque and random. It encodes nothing about the visitor and cannot be
 *   joined to any third-party identity graph, which is the distinction between
 *   measuring our own audience and tracking a person across the web.
 * - It is httpOnly. Nothing in the page needs to read it, and script that
 *   cannot read it cannot leak it to an embed.
 * - It is issued on the way *in*, so the same request that sets it can also use
 *   it. A newsletter POST is often the first request a visitor makes with a
 *   cookie jar, and reading only the incoming jar would record NULL for exactly
 *   the visitors the KPI most needs.
 */
class IssueVisitorId
{
    /** Chrome caps cookie lifetime at 400 days; anything longer is silently trimmed. */
    private const LIFETIME_MINUTES = 400 * 24 * 60;

    public const COOKIE = 'masar_vid';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->cookie(self::COOKIE);

        if (! is_string($id) || ! self::looksValid($id)) {
            $id = (string) Str::ulid();

            // Queued so it is attached to whatever response the route produces,
            // including a redirect.
            Cookie::queue(Cookie::make(
                name: self::COOKIE,
                value: $id,
                minutes: self::LIFETIME_MINUTES,
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        // Make it readable to this request, not only the next one.
        $request->attributes->set('visitor_id', $id);

        return $next($request);
    }

    /**
     * A cookie is client-supplied, so its shape is checked before it is stored.
     * A 26-character Crockford base32 ULID is the only thing we ever issue.
     */
    private static function looksValid(string $id): bool
    {
        return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id);
    }
}
