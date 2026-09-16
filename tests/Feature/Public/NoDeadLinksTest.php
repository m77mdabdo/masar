<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\MenuItem;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Support\Facades\Route;

/**
 * Three seeded footer links — /ar/team, /ar/corrections, /ar/advertise — pointed
 * at pages that did not exist, for the life of the project. Nothing caught it:
 * the menu rendered, the links were blue, and every test asserted the page they
 * sat on returned 200.
 *
 * A navigation item is a promise. This is the test that keeps it.
 */
it('resolves every url in every seeded menu', function (): void {
    $this->seed(CategorySeeder::class);
    $this->seed(DemoContentSeeder::class);

    $unresolved = [];

    foreach (MenuItem::query()->whereNotNull('url')->get() as $item) {
        $path = parse_url((string) $item->url, PHP_URL_PATH) ?: '';

        if ($path === '' || str_starts_with($path, 'http')) {
            continue;
        }

        // Asked of the router, not fetched: this is about whether the promise
        // can be kept at all, not about what the page happens to contain today.
        $matched = collect(Route::getRoutes()->getRoutes())->contains(
            fn ($route): bool => $route->matches(
                Illuminate\Http\Request::create($path, 'GET'),
                includingMethod: true,
            ),
        );

        if (! $matched) {
            $unresolved[] = $path;
        }
    }

    expect($unresolved)->toBe([], 'menu items pointing at no route: '.implode(', ', $unresolved));
});

it('serves the three footer pages that used to 404', function (string $path): void {
    $this->get($path)->assertOk();
})->with([
    '/ar/corrections',
    '/ar/advertise',
    '/ar/team',
]);

it('names nobody on the team page while no real person is marked an expert', function (): void {
    // Listing the demo people would invent a masthead of staff for a real
    // brand — §5's fabrication rule applied to people rather than quotes.
    // `people.is_expert` is never seeded true, so the page must say so instead.
    $this->seed(CategorySeeder::class);
    $this->seed(DemoContentSeeder::class);

    expect(App\Models\Person::where('is_expert', true)->count())->toBe(0);

    // Whitespace-normalised: the sentence wraps across lines in the template,
    // and asserting on the raw HTML makes the test a hostage to reflowing a
    // paragraph.
    $html = preg_replace('/\s+/u', ' ', $this->get('/ar/team')->assertOk()->getContent());

    expect($html)->toContain('غير حقيقيين');
});
