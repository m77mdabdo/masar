<?php

declare(strict_types=1);

use App\Filament\Pages\HomepageComposer;
use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\NavigationManager;
use App\Filament\Pages\NotFoundLog;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Models\HomepageLayout;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;

use function Pest\Livewire\livewire;

function admin(): User
{
    $admin = staff('super_admin');
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    return $admin->fresh();
}

it('renders the navigation manager', function (): void {
    $menu = Menu::factory()->create(['key' => 'header']);
    MenuItem::factory()->count(3)->create(['menu_id' => $menu->id]);

    $this->actingAs(admin());

    livewire(NavigationManager::class)->assertOk();
});

it('renders the homepage composer', function (): void {
    $layout = HomepageLayout::factory()->create(['is_active' => true]);
    $layout->sections()->create([
        'type' => 'leads', 'title' => ['ar' => 'الأبرز'], 'source' => 'auto',
        'config' => ['limit' => 4], 'sort_order' => 1, 'is_visible' => true,
    ]);

    $this->actingAs(admin());

    livewire(HomepageComposer::class)->assertOk();
});

it('serves both pages over http', function (): void {
    Menu::factory()->create(['key' => 'header']);
    HomepageLayout::factory()->create(['is_active' => true]);

    $this->actingAs(admin())->get(NavigationManager::getUrl())->assertOk();
    $this->actingAs(admin())->get(HomepageComposer::getUrl())->assertOk();
});

it('keeps both pages behind their permissions', function (): void {
    $writer = staff('writer');

    expect($writer->can('navigation.manage'))->toBeFalse()
        ->and($writer->can('homepage.manage'))->toBeFalse();

    $this->actingAs($writer)->get(NavigationManager::getUrl())->assertForbidden();
    $this->actingAs($writer)->get(HomepageComposer::getUrl())->assertForbidden();
});

it('renders the homepage preview for an inactive layout', function (): void {
    $layout = HomepageLayout::factory()->create(['is_active' => false]);
    $layout->sections()->create([
        'type' => 'leads', 'title' => ['ar' => 'الأبرز'], 'source' => 'auto',
        'config' => ['limit' => 4], 'sort_order' => 1, 'is_visible' => true,
    ]);

    // The point of a preview is seeing what is not live yet.
    $this->actingAs(admin())
        ->get(route('admin.homepage.preview', ['layout' => $layout]))
        ->assertOk()
        ->assertSee('غير نشط');
});

it('refuses the preview to someone without homepage rights', function (): void {
    $layout = HomepageLayout::factory()->create();

    $this->actingAs(staff('writer'))
        ->get(route('admin.homepage.preview', ['layout' => $layout]))
        ->assertForbidden();
});

it('reorders sections from the composer', function (): void {
    $layout = HomepageLayout::factory()->create(['is_active' => true]);

    foreach (['leads', 'tiles', 'stories'] as $i => $type) {
        $layout->sections()->create([
            'type' => $type, 'title' => ['ar' => $type], 'source' => 'auto',
            'config' => ['limit' => 3], 'sort_order' => $i + 1, 'is_visible' => true,
        ]);
    }

    $ids = $layout->sections()->orderBy('sort_order')->pluck('id')->all();

    $this->actingAs(admin());

    livewire(HomepageComposer::class)
        ->call('reorderSections', array_reverse($ids));

    expect($layout->sections()->orderBy('sort_order')->pluck('id')->all())
        ->toBe(array_reverse($ids));
});

it('ignores a section that belongs to another layout', function (): void {
    $mine = HomepageLayout::factory()->create(['is_active' => true]);
    $mine->sections()->create([
        'type' => 'leads', 'title' => ['ar' => 'x'], 'source' => 'auto',
        'config' => [], 'sort_order' => 1, 'is_visible' => true,
    ]);

    $theirs = HomepageLayout::factory()->create();
    $stranger = $theirs->sections()->create([
        'type' => 'tiles', 'title' => ['ar' => 'y'], 'source' => 'auto',
        'config' => [], 'sort_order' => 7, 'is_visible' => true,
    ]);

    $this->actingAs(admin());

    livewire(HomepageComposer::class)
        ->call('deleteSection', $stranger->id);

    // A payload naming someone else's section must do nothing.
    expect($stranger->fresh())->not->toBeNull();
});

it('renders the settings page', function (): void {
    $this->actingAs(admin());

    livewire(ManageSettings::class)->assertOk();
});

it('saves settings from the page and flushes the cache', function (): void {
    $this->actingAs(admin());

    livewire(ManageSettings::class)
        ->fillForm(['identity__site_name' => 'مسار الأعمال'])
        ->call('save');

    expect(setting('identity.site_name'))->toBe('مسار الأعمال');
});

it('keeps settings behind their permission', function (): void {
    $this->actingAs(staff('writer'))
        ->get(ManageSettings::getUrl())
        ->assertForbidden();
});

it('renders the redirects and 404 pages', function (): void {
    $this->actingAs(admin())
        ->get(RedirectResource::getUrl('index'))
        ->assertOk();

    $this->actingAs(admin())
        ->get(NotFoundLog::getUrl())
        ->assertOk();
});
