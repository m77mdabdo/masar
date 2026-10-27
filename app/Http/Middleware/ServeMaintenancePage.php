<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honours the «وضع الصيانة» switch in the settings page.
 *
 * The switch and its IP allowlist existed in the admin, saved and audited, and
 * nothing read them: an editor who turned maintenance on got a site that stayed
 * up. The help text promises the public site stops and the admin stays
 * available, so that is what this enforces — the admin, Livewire and Horizon
 * paths pass through, everything public answers 503 with errors/503.
 *
 * `php artisan down` remains the tool for a deploy; this is the editor's switch.
 */
class ServeMaintenancePage
{
    private const ALWAYS_OPEN = ['admin', 'admin/*', 'livewire/*', 'horizon', 'horizon/*', 'filament/*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::ALWAYS_OPEN) || ! setting('maintenance.enabled')) {
            return $next($request);
        }

        if (in_array($request->ip(), (array) setting('maintenance.allowlist', []), true)) {
            return $next($request);
        }

        abort(503);
    }
}
