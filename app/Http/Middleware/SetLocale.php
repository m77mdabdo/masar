<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Validates the locale segment against config and 404s on anything else.
 *
 * The segment is user input. Without this, `/xx/saudi` would set an unknown
 * locale, every translated lookup would fall through to the default, and the
 * site would serve Arabic content under a URL that claims to be something else —
 * which search engines index and never forget.
 *
 * A locale that exists but is disabled 404s too: `en` is architected from day
 * one and has no content yet, so serving an empty English site would be worse
 * than serving none.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale');
        $locales = (array) config('masar.locales');

        if (! array_key_exists($locale, $locales) || ! ($locales[$locale]['enabled'] ?? false)) {
            throw new NotFoundHttpException("Unknown locale [{$locale}].");
        }

        app()->setLocale($locale);

        // Direction and name are needed by every layout; resolving them once
        // here keeps templates from re-reading config on each partial.
        view()->share([
            'locale' => $locale,
            'direction' => $locales[$locale]['dir'] ?? 'rtl',
        ]);

        return $next($request);
    }
}
