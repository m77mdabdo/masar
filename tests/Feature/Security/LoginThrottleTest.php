<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Auth\Pages\Login;

use function Pest\Livewire\livewire;

/**
 * CLAUDE.md §5 requires login to be rate limited. It is — by Filament's own
 * `rateLimit(5)` inside the Login page, not by anything we wrote. A package
 * default is exactly the kind of guarantee this project has twice found
 * inverted, so it is measured here rather than read out of the vendor
 * directory. If an upgrade removes it, this fails.
 */
it('locks out a sixth failed login attempt', function (): void {
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);

    $attempt = fn (): object => livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'wrong'])
        ->call('authenticate');

    for ($i = 0; $i < 5; $i++) {
        $attempt();
    }

    expect(auth()->check())->toBeFalse();

    // The sixth is refused by the limiter rather than by the credentials, and
    // the right credentials do not get through it either.
    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'correct-horse'])
        ->call('authenticate');

    expect(auth()->check())->toBeFalse();
});

it('sends no more than two password-reset emails a minute, however often it is asked', function (): void {
    // Filament's rateLimit(2) on the request page, watched refusing rather
    // than read out of vendor/ (CLAUDE.md §7).
    \Illuminate\Support\Facades\Notification::fake();
    // A staff account: the panel sends resets only to users who may use it.
    $user = staff('writer');

    for ($i = 0; $i < 5; $i++) {
        livewire(\Filament\Auth\Pages\PasswordReset\RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request');
    }

    // Measured: one. Laravel's password broker allows one reset mail per
    // account per 60 seconds, stricter than Filament's two per minute; the
    // property is that five requests cannot become five emails.
    $sent = \Illuminate\Support\Facades\Notification::sent($user, \Filament\Auth\Notifications\ResetPassword::class)->count();
    expect($sent)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(2);
});
