<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Topic;
use App\Queries\MenuTreeQuery;

/**
 * The navigation UI tells editors that an entity link survives a slug change and
 * a manual URL does not. Both halves are asserted here so that claim is provable
 * rather than merely confident.
 */
it('follows the entity when its slug changes', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);
    $menu = Menu::factory()->create(['key' => 'header-'.uniqid()]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'السعودية'],
        'url' => null,
        'linkable_type' => $category->getMorphClass(),
        'linkable_id' => $category->getKey(),
    ]);

    $before = app(MenuTreeQuery::class)($menu->key, 'ar')->first()['url'];

    $category->forceFill(['slug' => 'saudi-markets'])->save();

    $after = app(MenuTreeQuery::class)($menu->key, 'ar')->first()['url'];

    expect($before)->toBe('/ar/saudi')
        ->and($after)->toBe('/ar/saudi-markets');
});

it('leaves a manual url pointing at the old slug', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);
    $menu = Menu::factory()->create(['key' => 'header-'.uniqid()]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'السعودية'],
        'url' => '/ar/saudi',
        'linkable_type' => null,
        'linkable_id' => null,
    ]);

    $category->forceFill(['slug' => 'saudi-markets'])->save();

    // Unchanged, and now a 404 — this is the cost the helper text warns about.
    expect(app(MenuTreeQuery::class)($menu->key, 'ar')->first()['url'])->toBe('/ar/saudi');
});

it('builds a prefixed url for each entity type', function (): void {
    $topic = Topic::factory()->create(['slug' => 'vision-2030']);
    $menu = Menu::factory()->create(['key' => 'header-'.uniqid()]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'رؤية 2030'],
        'url' => null,
        'linkable_type' => $topic->getMorphClass(),
        'linkable_id' => $topic->getKey(),
    ]);

    expect(app(MenuTreeQuery::class)($menu->key, 'ar')->first()['url'])->toBe('/ar/topics/vision-2030');
});

it('always prefixes the locale', function (): void {
    $category = Category::factory()->create(['slug' => 'business']);
    $menu = Menu::factory()->create(['key' => 'header-'.uniqid()]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'url' => null,
        'linkable_type' => $category->getMorphClass(),
        'linkable_id' => $category->getKey(),
    ]);

    expect(app(MenuTreeQuery::class)($menu->key, 'en')->first()['url'])->toBe('/en/business');
});

it('falls back to the stored url when the entity is gone', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);
    $menu = Menu::factory()->create(['key' => 'header-'.uniqid()]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'السعودية'],
        'url' => '/ar/fallback',
        'linkable_type' => $category->getMorphClass(),
        'linkable_id' => $category->getKey(),
    ]);

    $category->delete();

    // A dangling link renders something rather than crashing the header.
    expect(app(MenuTreeQuery::class)($menu->key, 'ar')->first()['url'])->toBe('/ar/fallback');
});

it('returns the translated label for the requested locale', function (): void {
    $menu = Menu::factory()->create(['key' => 'header-'.uniqid()]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'الأعمال', 'en' => 'Business'],
    ]);

    expect(app(MenuTreeQuery::class)($menu->key, 'ar')->first()['label'])->toBe('الأعمال')
        ->and(app(MenuTreeQuery::class)($menu->key, 'en')->first()['label'])->toBe('Business');
});
