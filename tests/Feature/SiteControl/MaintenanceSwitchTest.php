<?php

declare(strict_types=1);

use App\Support\Settings;

/**
 * The «وضع الصيانة» switch was saved, audited, and read by nothing. These drive
 * the switch the way an editor does — through Settings — and read the public
 * response.
 */
it('closes the public site with the maintenance page when the switch is on', function (): void {
    app(Settings::class)->set('maintenance.enabled', true);

    $this->get('/ar/about')->assertStatus(503)->assertSee('مسار قيد الصيانة', false);
});

it('leaves the public site open when the switch is off', function (): void {
    app(Settings::class)->set('maintenance.enabled', false);

    $this->get('/ar/about')->assertOk();
});

it('lets an allowlisted address through maintenance', function (): void {
    app(Settings::class)->setMany(['maintenance.enabled' => true, 'maintenance.allowlist' => ['10.1.2.3']]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->get('/ar/about')->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->get('/ar/about')->assertStatus(503);
});

it('keeps the admin reachable during maintenance, as the switch promises', function (): void {
    app(Settings::class)->set('maintenance.enabled', true);

    $this->get('/admin/login')->assertOk();
});
