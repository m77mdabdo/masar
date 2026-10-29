<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public site's Content-Security-Policy.
 *
 * One nonce per response: Vite's script tags and the big story's loader carry
 * it, and no other inline script exists. JSON-LD blocks are data, which CSP
 * does not execute. Each permission is here because something on the page
 * needs it:
 *
 * - 'unsafe-eval' — Alpine's standard build evaluates its expressions. The
 *   CSP build would remove it at the cost of rewriting every x-data; noted in
 *   DEFERRED.md rather than done silently.
 * - style-src-attr 'unsafe-inline' — inline style attributes (`--focal`, the
 *   carousel's hidden slides). Style *elements* stay 'self' only.
 * - img/media: 'self' plus the media disk's own host (R2 in production).
 * - frame-src: youtube-nocookie, which loads only after a reader's click.
 *
 * The admin panel runs its own middleware stack and is not covered here.
 */
final class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(18));
        Vite::useCspNonce($nonce);

        $response = $next($request);

        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $media = $this->mediaOrigin();

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
            "style-src 'self'",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: {$media}",
            "media-src 'self' {$media}",
            "font-src 'self'",
            "connect-src 'self'",
            'frame-src https://www.youtube-nocookie.com',
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        if (app()->environment('production')) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', array_map('trim', $directives));
    }

    /** The origin media URLs are built on, when it is not this site's own. */
    private function mediaOrigin(): string
    {
        $url = (string) (config('filesystems.disks.'.config('media-library.disk_name').'.url') ?: config('app.url'));
        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : '';
    }
}
