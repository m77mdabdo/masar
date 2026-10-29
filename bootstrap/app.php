<?php

use App\Http\Middleware\HandleMissingPages;
use App\Http\Middleware\IssueVisitorId;
use App\Http\Middleware\SendDemoRobotsHeader;
use App\Http\Middleware\ServeMaintenancePage;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Behind Cloudflare or a load balancer the connecting address is the
         * proxy's, so every reader would share one rate-limit bucket and
         * HTTPS would read as HTTP. TRUSTED_PROXIES names them (comma list,
         * or `*` when the only route in is the proxy). Unset, nothing is
         * trusted — the safe default for a bare server.
         */
        if (filled($proxies = env('TRUSTED_PROXIES'))) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        /*
         * Redirects and 404 logging both hang off the same missing-page path.
         * Global, not the `web` group: an unknown URL never matches a route, so
         * it 404s before any route middleware runs.
         */
        $middleware->append(HandleMissingPages::class);

        // Demo content must not be indexed under the brand, in any format.
        $middleware->append(SendDemoRobotsHeader::class);

        /*
         * Returning audience is the primary KPI and there are no reader
         * accounts, so a first-party anonymous cookie is the only handle we
         * have. In the `web` group because it needs the cookie stack, and
         * prepended so a POST that subscribes can read the id it just issued.
         */
        $middleware->web(prepend: [IssueVisitorId::class]);

        // The editor's maintenance switch. Admin paths are exempt inside it.
        $middleware->web(append: [ServeMaintenancePage::class]);

        // The locale segment is user input; SetLocale is what validates it.
        $middleware->alias([
            'locale' => SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
