<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-factor is mandatory for anyone who can publish, and optional for everyone
 * else.
 *
 * Filament's own EnsureMultiFactorAuthenticationIsEnabled applies to the whole
 * panel or not at all, and the panel decides that at route-registration time —
 * before there is a user to ask. This replaces it so the requirement can depend
 * on the person: a designer is not forced through TOTP setup to crop an image,
 * but nobody reaches the publish button without a second factor.
 */
class EnsurePublishersUseTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if ($user === null) {
            return $next($request);
        }

        if (! method_exists($user, 'requiresTwoFactor') || ! $user->requiresTwoFactor()) {
            return $next($request);
        }

        if (MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return $next($request);
        }

        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }
}
