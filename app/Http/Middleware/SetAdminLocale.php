<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel in the signed-in staff member's language.
 *
 * Direction is not set here: Filament reads it from its own translation
 * (`filament-panels::layout.direction`), so Arabic and Urdu render RTL and the
 * other four LTR from the locale alone, as the panel always has for Arabic.
 */
final class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) ($request->user()?->locale ?? '');

        app()->setLocale(Locales::exists($locale) ? $locale : Locales::default());

        return $next($request);
    }
}
