<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idles the admin panel out sooner than the rest of the application.
 *
 * The panel holds unpublished embargoed stories and reader data, and editors
 * work on shared newsroom machines. The app-wide session lifetime is tuned for
 * convenience; this one is tuned for a laptop left open in a meeting room.
 */
class EnforceAdminSessionLifetime
{
    private const KEY = 'masar.admin.last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $lifetime = (int) config('masar.admin.session_lifetime', 60);

        if ($lifetime <= 0 || ! Filament::auth()->check()) {
            return $next($request);
        }

        $lastActivity = $request->session()->get(self::KEY);

        if ($lastActivity !== null && (now()->timestamp - (int) $lastActivity) > ($lifetime * 60)) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(Filament::getLoginUrl());
        }

        $request->session()->put(self::KEY, now()->timestamp);

        return $next($request);
    }
}
