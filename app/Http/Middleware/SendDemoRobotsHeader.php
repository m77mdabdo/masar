<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * `X-Robots-Tag: noindex, nofollow` on every response while demo mode is on.
 *
 * The robots meta tag only reaches HTML. The RSS feeds carry every demo
 * article's text, and images and JSON carry no meta at all; robots.txt asks a
 * crawler not to fetch, but does not stop a page fetched another way from being
 * indexed. The header covers every response type. Global, so a 404 or an
 * unrouted path gets it too.
 */
class SendDemoRobotsHeader
{
    public function __construct(private readonly DemoMode $demo) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $on = $this->demo->isOn();
        } catch (Throwable) {
            // A setting that cannot be read is no reason to become indexable:
            // closed is the switch's own default.
            $on = true;
        }

        if ($on) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
