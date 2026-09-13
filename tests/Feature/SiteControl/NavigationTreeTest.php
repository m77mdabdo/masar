<?php

declare(strict_types=1);

use App\Actions\Navigation\SaveMenuTree;
use App\Actions\Navigation\ValidateMenuTree;
use App\Exceptions\InvalidMenuTree;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Queries\MenuTreeQuery;
use Illuminate\Support\Facades\DB;

function menuWithItems(int $count = 3, ?string $key = null): Menu
{
    // `menus.key` is unique, so a helper used twice in one test needs distinct keys.
    $menu = Menu::factory()->create(['key' => $key ?? 'menu-'.uniqid()]);

    foreach (range(1, $count) as $i) {
        MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'label' => ['ar' => "عنصر {$i}"],
            'sort_order' => $i,
        ]);
    }

    return $menu->refresh();
}

it('reorders items within a level', function (): void {
    $menu = menuWithItems();
    [$a, $b, $c] = $menu->items()->orderBy('sort_order')->get()->all();

    app(SaveMenuTree::class)($menu, [
        ['id' => $c->id, 'children' => []],
        ['id' => $a->id, 'children' => []],
        ['id' => $b->id, 'children' => []],
    ]);

    expect($menu->items()->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$c->id, $a->id, $b->id]);
});

it('reparents an item under another', function (): void {
    $menu = menuWithItems();
    [$a, $b] = $menu->items()->orderBy('sort_order')->get()->all();

    app(SaveMenuTree::class)($menu, [
        ['id' => $a->id, 'children' => [['id' => $b->id, 'children' => []]]],
    ]);

    expect($b->fresh()->parent_id)->toBe($a->id)
        ->and($a->fresh()->parent_id)->toBeNull();
});

it('accepts a tree at the maximum depth', function (): void {
    $menu = menuWithItems();
    [$a, $b, $c] = $menu->items()->orderBy('sort_order')->get()->all();

    app(SaveMenuTree::class)($menu, [
        ['id' => $a->id, 'children' => [
            ['id' => $b->id, 'children' => [
                ['id' => $c->id, 'children' => []],
            ]],
        ]],
    ]);

    expect($c->fresh()->parent_id)->toBe($b->id);
});

it('rejects a tree deeper than the limit', function (): void {
    $menu = menuWithItems(4);
    [$a, $b, $c, $d] = $menu->items()->orderBy('sort_order')->get()->all();

    expect(fn () => app(SaveMenuTree::class)($menu, [
        ['id' => $a->id, 'children' => [
            ['id' => $b->id, 'children' => [
                ['id' => $c->id, 'children' => [
                    ['id' => $d->id, 'children' => []],
                ]],
            ]],
        ]],
    ]))->toThrow(InvalidMenuTree::class);

    // Nothing is written on failure: a half-saved menu breaks the public header.
    expect($d->fresh()->parent_id)->toBeNull();
});

it('rejects an item that is its own parent', function (): void {
    $menu = menuWithItems();
    $a = $menu->items()->first();

    expect(fn () => app(SaveMenuTree::class)($menu, [
        ['id' => $a->id, 'children' => [['id' => $a->id, 'children' => []]]],
    ]))->toThrow(InvalidMenuTree::class);
});

it('names the cycle precisely rather than calling it a duplicate', function (): void {
    $problems = app(ValidateMenuTree::class)([
        ['id' => 1, 'children' => [['id' => 1, 'children' => []]]],
    ]);

    expect($problems[0])->toContain('أبًا لنفسه');
});

it('rejects an item belonging to another menu', function (): void {
    $menu = menuWithItems();
    $other = menuWithItems(1);

    $stranger = $other->items()->first();

    expect(fn () => app(SaveMenuTree::class)($menu, [
        ['id' => $stranger->id, 'children' => []],
    ]))->toThrow(InvalidMenuTree::class);
});

it('hides an item outside its scheduling window', function (): void {
    $menu = Menu::factory()->create(['key' => 'header']);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'قادم'],
        'starts_at' => now()->addWeek(),
    ]);
    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'منتهٍ'],
        'ends_at' => now()->subDay(),
    ]);
    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'ظاهر'],
    ]);

    $tree = app(MenuTreeQuery::class)('header', 'ar');

    expect($tree)->toHaveCount(1)
        ->and($tree->first()['label'])->toBe('ظاهر');
});

it('respects device visibility flags', function (): void {
    $menu = Menu::factory()->create(['key' => 'header']);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => ['ar' => 'جوال فقط'],
        'show_desktop' => false,
        'show_mobile' => true,
    ]);

    expect(app(MenuTreeQuery::class)('header', 'ar', 'desktop'))->toHaveCount(0)
        ->and(app(MenuTreeQuery::class)('header', 'ar', 'mobile'))->toHaveCount(1);
});

it('hides an inactive item', function (): void {
    $menu = Menu::factory()->create(['key' => 'header']);
    MenuItem::factory()->create(['menu_id' => $menu->id, 'is_active' => false]);

    expect(app(MenuTreeQuery::class)('header', 'ar'))->toHaveCount(0);
});

it('builds the menu in a constant number of queries', function (): void {
    $menu = menuWithItems(12);

    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    app(MenuTreeQuery::class)('header', 'ar');

    // Navigation renders on every page; a query per level would be fatal.
    expect($count)->toBeLessThanOrEqual(4);
});
