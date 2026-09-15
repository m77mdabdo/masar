<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('creates an owner who can reach the control layer', function (): void {
    $this->artisan('masar:owner', ['email' => 'owner@masar.test', '--name' => 'المالك'])
        ->expectsQuestion('Password', 'Mhmd123456789')
        ->assertSuccessful();

    $user = User::firstWhere('email', 'owner@masar.test');

    // The gap this command exists to close: the seeded editorial roles hold
    // none of these, so a fresh install had nobody who could open the composer.
    expect($user)->not->toBeNull()
        ->and($user->hasRole('super_admin'))->toBeTrue()
        ->and($user->can('homepage.manage'))->toBeTrue()
        ->and($user->can('settings.manage'))->toBeTrue()
        ->and($user->can('intelligence.configure'))->toBeTrue();
});

it('stores the password hashed, never in the clear', function (): void {
    $this->artisan('masar:owner', ['email' => 'owner@masar.test', '--name' => 'المالك'])
        ->expectsQuestion('Password', 'Mhmd123456789')
        ->assertSuccessful();

    $stored = User::firstWhere('email', 'owner@masar.test')->password;

    expect($stored)->not->toBe('Mhmd123456789')
        ->and(Hash::check('Mhmd123456789', $stored))->toBeTrue();
});

it('refuses a password short enough to brute force on an account that can publish', function (): void {
    $this->artisan('masar:owner', ['email' => 'owner@masar.test', '--name' => 'المالك'])
        ->expectsQuestion('Password', 'short')
        ->assertFailed();

    expect(User::where('email', 'owner@masar.test')->exists())->toBeFalse();
});

it('refuses something that is not an email', function (): void {
    $this->artisan('masar:owner', ['email' => 'not-an-email'])->assertFailed();
});

it('promotes an existing account and can retire the one it replaces', function (): void {
    $old = User::factory()->create(['email' => 'admin@masar.test']);
    $me = User::factory()->create(['email' => 'me@masar.test', 'name' => 'أنا']);
    $was = $me->password;

    $this->artisan('masar:owner', [
        'email' => 'me@masar.test',
        '--replace' => 'admin@masar.test',
    ])
        ->expectsQuestion('New password (blank to keep the current one)', '')
        ->assertSuccessful();

    expect($me->fresh()->hasRole('super_admin'))->toBeTrue()
        ->and($me->fresh()->password)->toBe($was)          // blank kept it
        ->and(User::whereKey($old->id)->exists())->toBeFalse();
});
