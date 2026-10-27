<?php

declare(strict_types=1);

use App\Actions\Navigation\SaveMenuTree;
use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\NavigationManager;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Livewire\livewire;

/**
 * MenuItemPolicy and SettingPolicy were registered and asked by nothing: both
 * pages gated on a permission string, so the policies read as protection while
 * protecting nothing. These tests swap each policy for one that refuses
 * everything — if the page still opens, it is not asking the policy.
 *
 * The two surfaces have the widest blast radius in the admin: one row changes
 * every public page.
 */
final class RefuseEverythingPolicy
{
    public function __call(string $ability, array $arguments): bool
    {
        return false;
    }
}

function navigator(): User
{
    $user = staff('editor_in_chief');
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    test()->actingAs($user = $user->fresh());

    return $user;
}

function headerMenu(int $items = 3): Menu
{
    $menu = Menu::factory()->create(['key' => 'header']);

    foreach (range(1, $items) as $i) {
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => ['ar' => "عنصر {$i}"], 'sort_order' => $i, 'is_active' => true]);
    }

    return $menu->refresh();
}

/** Takes the permission away while the page is already open in the browser. */
function demote(User $user): void
{
    $user->syncRoles(['writer']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

// ── the pages ask the policy ────────────────────────────────────────────────

it('opens the navigation manager by asking MenuItemPolicy, not the permission string', function (): void {
    navigator();
    headerMenu();

    livewire(NavigationManager::class)->assertOk();

    Gate::policy(MenuItem::class, RefuseEverythingPolicy::class);

    livewire(NavigationManager::class)->assertForbidden();
});

it('opens the settings page by asking SettingPolicy, not the permission string', function (): void {
    navigator();

    livewire(ManageSettings::class)->assertOk();

    Gate::policy(Setting::class, RefuseEverythingPolicy::class);

    livewire(ManageSettings::class)->assertForbidden();
});

// ── a page opened before a demotion cannot write ────────────────────────────
//
// Filament's CanAuthorizeAccess re-runs canAccess() in hydrate on every Livewire
// request, so these held before the policies were wired — the October audit
// claimed otherwise and was wrong. Kept because a package protection is
// unverified until it has been watched refusing (CLAUDE.md §7); an upgrade that
// drops the hydrate check fails here.

it('refuses a menu toggle from a user demoted after opening the page', function (): void {
    $user = navigator();
    $item = headerMenu()->items()->first();
    $page = livewire(NavigationManager::class);

    demote($user);

    $page->call('toggleItem', $item->id)->assertForbidden();
    expect($item->fresh()->is_active)->toBeTrue();
});

it('refuses a menu delete from a user demoted after opening the page', function (): void {
    $user = navigator();
    $item = headerMenu()->items()->first();
    $page = livewire(NavigationManager::class);

    demote($user);

    $page->call('deleteItem', $item->id)->assertForbidden();
    expect(MenuItem::query()->whereKey($item->id)->exists())->toBeTrue();
});

it('refuses a menu reorder from a user demoted after opening the page', function (): void {
    $user = navigator();
    [$a, $b, $c] = headerMenu()->items()->orderBy('sort_order')->get()->all();
    $page = livewire(NavigationManager::class);

    demote($user);

    $page->call('saveTree', [['id' => $c->id], ['id' => $a->id], ['id' => $b->id]])->assertForbidden();
    expect($c->fresh()->sort_order)->toBe(3);
});

it('refuses a settings save from a user demoted after opening the page', function (): void {
    $user = navigator();
    $page = livewire(ManageSettings::class);

    demote($user);

    $page->call('save')->assertForbidden();
});

it('refuses a reorder from an actor without navigation rights at the action itself', function (): void {
    $menu = headerMenu();
    [$a, $b] = $menu->items()->orderBy('sort_order')->get()->all();

    expect(fn () => app(SaveMenuTree::class)($menu, [['id' => $b->id], ['id' => $a->id]], staff('writer')))
        ->toThrow(AuthorizationException::class);
});

// ── writes are audited ──────────────────────────────────────────────────────

it('writes an audit entry, with the editor as causer, for every item a reorder moves', function (): void {
    $user = navigator();
    [$a, $b, $c] = headerMenu()->items()->orderBy('sort_order')->get()->all();

    livewire(NavigationManager::class)
        ->call('saveTree', [['id' => $c->id], ['id' => $a->id], ['id' => $b->id]]);

    $entries = Activity::query()
        ->where('subject_type', 'menu_item')
        ->where('event', 'updated')
        ->get();

    // All three moved; a reorder used to be a bulk update that logged nothing.
    expect($entries->pluck('subject_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id, $c->id])->sort()->values()->all())
        ->and($entries->pluck('causer_id')->unique()->all())->toBe([$user->id])
        ->and($entries->firstWhere('subject_id', $c->id)->attribute_changes['attributes']['sort_order'])->toBe(1);
});

it('writes an audit entry for a settings save', function (): void {
    $user = navigator();

    livewire(ManageSettings::class)
        ->set('data.site__name', 'مسار التجريبي')
        ->call('save');

    expect(Activity::query()->where('subject_type', 'setting')->where('causer_id', $user->id)->exists())->toBeTrue();
});
