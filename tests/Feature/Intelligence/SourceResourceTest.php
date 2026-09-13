<?php

declare(strict_types=1);

use App\Filament\Resources\Sources\Pages\ListSources;
use App\Filament\Resources\Sources\SourceResource;
use App\Models\Source;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function userWithIntelligence(bool $allowed = true): User
{
    Permission::findOrCreate('intelligence.configure', 'web');
    $role = Role::findOrCreate($allowed ? 'ops' : 'writer-only', 'web');

    if ($allowed) {
        $role->givePermissionTo('intelligence.configure');
    }

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('shows source health to someone who configures monitoring', function (): void {
    $this->actingAs(userWithIntelligence());

    Source::factory()->create(['name' => 'مصدر سليم', 'last_success_at' => now()]);
    Source::factory()->failing(3)->create(['name' => 'مصدر يفشل']);

    Livewire::test(ListSources::class)
        ->assertOk()
        ->assertSee('مصدر سليم')
        ->assertSee('مصدر يفشل')
        // The streak against its threshold, not a bare number: "3" means
        // nothing without knowing what disables it.
        ->assertSee('3/'.config('masar.intelligence.failure_threshold'));
});

it('refuses the resource to someone without the permission', function (): void {
    $this->actingAs(userWithIntelligence(allowed: false));

    expect(SourceResource::canViewAny())->toBeFalse();
});

it('counts failing sources in the navigation badge', function (): void {
    Source::factory()->count(2)->failing(1)->create();
    Source::factory()->create();

    // The badge is how a failure gets noticed without anyone going to look.
    expect(SourceResource::getNavigationBadge())->toBe('2');
});

it('shows no badge when every source is working', function (): void {
    Source::factory()->count(3)->create();

    expect(SourceResource::getNavigationBadge())->toBeNull();
});

it('clears the failure streak when a source is reactivated', function (): void {
    $this->actingAs(userWithIntelligence());

    $source = Source::factory()->failing(5)->create(['is_active' => false]);

    Livewire::test(ListSources::class)
        ->callTableAction('reactivate', $source)
        ->assertHasNoTableActionErrors();

    // Leaving the streak at the threshold would disable it again on its next
    // failure, which looks like the fix did not work.
    expect($source->refresh()->is_active)->toBeTrue()
        ->and($source->consecutive_failures)->toBe(0);
});
