<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel in the staff member's language, before and after sign-in.
 *
 * Signed in, it is `users.locale`, so each person's choice is their own and
 * survives logging out. Before sign-in, the login page offers the six
 * editions as `?lang=`, kept in the session; failing that, the edition the
 * visitor last read on the public site; failing that, Arabic. No cookie is
 * added for this: the session and the edition cookie already exist, and the
 * cookie policy lists only what the site sets.
 *
 * Direction is not set here: Filament reads it from its own translation
 * (`filament-panels::layout.direction`), so Arabic and Urdu render RTL and the
 * other four LTR from the locale alone.
 */
final class SetAdminLocale
{
    private const SESSION = 'admin_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = (string) $request->query('lang', '');
        if ($request->user() === null && Locales::exists($chosen) && $request->hasSession()) {
            $request->session()->put(self::SESSION, $chosen);
        }

        $candidates = [
            (string) ($request->user()?->locale ?? ''),
            $request->hasSession() ? (string) $request->session()->get(self::SESSION, '') : '',
            (string) $request->cookie(SetLocale::COOKIE, ''),
        ];

        $locale = collect($candidates)->first(fn (string $code): bool => Locales::exists($code)) ?? Locales::default();
        app()->setLocale($locale);

        return $next($request);
    }
}
