<?php

declare(strict_types=1);

use App\Filament\Pages\EditorialPipeline;
use App\Filament\Resources\Articles\ArticleResource;
use App\Http\Middleware\EnforceAdminSessionLifetime;
use App\Http\Middleware\EnsurePublishersUseTwoFactor;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

/**
 * Two-factor is mandatory for anyone who can publish and optional for everyone
 * else. These go through the HTTP stack rather than Livewire, because the rule
 * lives in middleware.
 */
it('redirects a publish-capable user without 2FA to enrolment', function (): void {
    $chief = staff('editor_in_chief');

    expect($chief->requiresTwoFactor())->toBeTrue()
        ->and($chief->hasTwoFactorEnabled())->toBeFalse();

    $this->actingAs($chief)
        ->get(ArticleResource::getUrl('index'))
        ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
});

it('lets a publish-capable user in once enrolled', function (): void {
    $chief = staff('editor_in_chief');
    $chief->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($chief->fresh())
        ->get(ArticleResource::getUrl('index'))
        ->assertOk();
});

it('does not force 2FA on a user who cannot publish', function (): void {
    $writer = staff('writer');

    expect($writer->can('article.publish'))->toBeFalse()
        ->and($writer->requiresTwoFactor())->toBeFalse();

    // A designer should not be pushed through TOTP setup to crop an image.
    $this->actingAs($writer)
        ->get(ArticleResource::getUrl('index'))
        ->assertOk();
});

it('blocks the pipeline board too, not just the article list', function (): void {
    $chief = staff('editor_in_chief');

    $this->actingAs($chief)
        ->get(EditorialPipeline::getUrl())
        ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
});

it('records when the second factor was confirmed', function (): void {
    $chief = staff('editor_in_chief');
    $chief->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    expect($chief->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('clears the confirmation timestamp when 2FA is removed', function (): void {
    $chief = staff('editor_in_chief');
    $chief->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $chief->saveAppAuthenticationSecret(null);

    expect($chief->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and($chief->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('never exposes the secret in a serialised user', function (): void {
    $chief = staff('editor_in_chief');
    $chief->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $array = $chief->fresh()->toArray();

    expect($array)->not->toHaveKey('app_authentication_secret')
        ->and($array)->not->toHaveKey('app_authentication_recovery_codes');
});

it('stores the secret encrypted at rest', function (): void {
    $chief = staff('editor_in_chief');
    $chief->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $raw = DB::table('users')
        ->where('id', $chief->id)
        ->value('app_authentication_secret');

    expect($raw)->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($chief->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP');
});

it('denies the panel entirely to a user with no editorial permission', function (): void {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(ArticleResource::getUrl('index'))
        ->assertForbidden();
});

/**
 * Wiring, not outcome.
 *
 * The behaviour tests above would keep passing if Filament stopped calling
 * isMultiFactorAuthenticationRequired() at route-registration time — the
 * middleware would simply never run, and every "redirect" assertion would fail
 * loudly only for publishers who already lack 2FA. These assert the mechanism
 * the behaviour depends on, so an upstream change breaks the build here first.
 */
it('registers the conditional 2FA middleware on the panel', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getMultiFactorAuthenticationRequiredMiddleware())
        ->toBe(EnsurePublishersUseTwoFactor::class);
});

it('still treats multi-factor as required at the panel level', function (): void {
    // If this flips to false, Filament stops applying the middleware at all and
    // the conditional enforcement silently disappears.
    expect(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired())->toBeTrue();
});

it('applies the 2FA middleware to resource routes', function (): void {
    $route = collect(app('router')->getRoutes())
        ->first(fn ($r): bool => $r->getName() === 'filament.admin.resources.articles.index');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain(EnsurePublishersUseTwoFactor::class);
});

it('applies the 2FA middleware to custom pages too', function (): void {
    $route = collect(app('router')->getRoutes())
        ->first(fn ($r): bool => $r->getName() === 'filament.admin.pages.pipeline');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain(EnsurePublishersUseTwoFactor::class);
});

it('registers an app authentication provider', function (): void {
    $providers = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders();

    expect($providers)->not->toBeEmpty()
        ->and(array_map('get_class', array_values($providers)))
        ->toContain(AppAuthentication::class);
});

it('enforces the shorter admin session lifetime on panel routes', function (): void {
    $route = collect(app('router')->getRoutes())
        ->first(fn ($r): bool => $r->getName() === 'filament.admin.resources.articles.index');

    expect($route->gatherMiddleware())
        ->toContain(EnforceAdminSessionLifetime::class);
});
