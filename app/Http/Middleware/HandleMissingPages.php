<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Redirects\NormalisePath;
use App\Jobs\RecordNotFound;
use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Everything that happens when a URL does not resolve: send the reader onward if
 * an editor has mapped it, and record it if nobody has.
 *
 * Global rather than route middleware, because an unknown URL never matches a
 * route and so never reaches the `web` group. It runs on the way *out*, so a
 * live page pays nothing — the lookup only happens once the response is a 404.
 *
 * The 404 log lives here rather than in the exception handler because Laravel
 * treats NotFoundHttpException as non-reportable, so a `report()` callback for
 * it is never called. Doing it here also means a path that *does* have a
 * redirect is never logged as dead, which is correct: it isn't.
 */
class HandleMissingPages
{
    public function __construct(
        private readonly NormalisePath $normalise,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! $request->isMethod('GET')) {
            return $response;
        }

        $path = ($this->normalise)($request->path());

        $redirect = Redirect::query()->where('from_path', $path)->first();

        if ($redirect !== null) {
            // Counted without touching updated_at or firing model events — a hit
            // is traffic, not an edit.
            DB::table('redirects')
                ->where('id', $redirect->getKey())
                ->update([
                    'hits' => DB::raw('hits + 1'),
                    'last_hit_at' => now(),
                ]);

            return redirect($redirect->to_path, $redirect->status_code);
        }

        $this->record($path, $request->headers->get('referer'));

        return $response;
    }

    private function record(string $path, ?string $referrer): void
    {
        if ($path === '/' || RecordNotFound::shouldIgnore($path)) {
            return;
        }

        try {
            RecordNotFound::dispatch($path, $referrer);
        } catch (Throwable) {
            // A logging failure must never become the visitor's problem: they
            // already got a 404, and turning it into a 500 helps nobody.
        }
    }
}
