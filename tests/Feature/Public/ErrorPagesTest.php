<?php

declare(strict_types=1);

use App\Models\Category;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Stock English Laravel pages on an Arabic-first publication were the last
 * thing a lost reader saw. Each status is rendered in both editions and read
 * back, not just asserted to exist.
 */
/**
 * Each status is raised from a real route and rendered through the full HTTP
 * kernel, as a reader would meet it. Four segments, because the public
 * `{locale}/{category}/{slug}` route is unconstrained and swallows any shorter
 * path registered after it.
 */
beforeEach(function (): void {
    foreach (['ar', 'en'] as $locale) {
        Route::get("/{$locale}/__test/error/{code}", fn (string $code) => abort((int) $code))->middleware('web');
        Route::get("/{$locale}/__test/error-thrown/500", fn () => throw new RuntimeException('boom'))->middleware('web');
    }
});

$headings = [
    403 => ['ar' => 'لا تملك صلاحية الوصول إلى هذه الصفحة', 'en' => 'You do not have access to this page'],
    404 => ['ar' => 'لم نجد هذه الصفحة', 'en' => 'We could not find this page'],
    419 => ['ar' => 'انتهت صلاحية هذه الصفحة', 'en' => 'This page has expired'],
    429 => ['ar' => 'طلبات كثيرة في وقت قصير', 'en' => 'Too many requests'],
    500 => ['ar' => 'حدث خطأ من جهتنا', 'en' => 'Something went wrong on our side'],
    503 => ['ar' => 'مسار قيد الصيانة', 'en' => 'MASAR is down for maintenance'],
];

$cases = [];
foreach ($headings as $code => $byLocale) {
    foreach ($byLocale as $locale => $heading) {
        $cases["{$code} {$locale}"] = [$code, $locale, $heading];
    }
}

it('renders each error in the edition the reader was in', function (int $code, string $locale, string $heading): void {
    $html = $this->get("/{$locale}/__test/error/{$code}")->assertStatus($code)->getContent();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';

    expect($html)
        ->toContain("<html lang=\"{$locale}\" dir=\"{$dir}\"")
        ->toContain($heading)
        ->toContain('noindex')
        ->not->toContain('Not Found')
        ->not->toContain('Server Error');
})->with($cases);

it('serves the Arabic 404 for a real missing URL', function (): void {
    $this->get('/ar/no-such-page-at-all')
        ->assertNotFound()
        ->assertSee('لم نجد هذه الصفحة', false)
        ->assertSee('<html lang="ar" dir="rtl"', false);
});

it('serves the 404 for a path that matches no route at all', function (): void {
    // No route means no `web` middleware: nothing shares $errors or starts a
    // session. The page rendered as a 500 here before the error bag was shared.
    $this->get('/xx/nowhere')->assertNotFound()->assertSee('لم نجد هذه الصفحة', false);
    $this->get('/zz')->assertNotFound()->assertSee('لم نجد هذه الصفحة', false);
});

it('offers search and the main sections on the 404', function (): void {
    Category::factory()->create(['slug' => 'saudi', 'is_active' => true, 'parent_id' => null]);
    Category::factory()->create(['slug' => 'hidden-desk', 'is_active' => false, 'parent_id' => null]);

    $html = $this->get('/ar/no-such-page-at-all')->assertNotFound()->getContent();

    expect($html)
        ->toContain('action="'.route('web.search', 'ar').'"')
        ->toContain('href="'.route('web.category.show', ['ar', 'saudi']).'"')
        ->not->toContain('hidden-desk');
});

it('renders the 500 page with the database gone', function (): void {
    // The 500 page must survive the failure that caused it, and that is most
    // often the database. So the route takes the database away — connection
    // pointed at a dead port, settings memo and cache emptied — and only then
    // fails. If the page reads a setting or runs a query, it throws while
    // rendering and the reader gets Laravel's stock page instead of this one.
    config(['app.debug' => false]);
    $connection = config('database.default');
    $port = config("database.connections.{$connection}.port");

    Route::get('/ar/__test/error-dbless/500', function () use ($connection): never {
        config(["database.connections.{$connection}.port" => 1]);
        DB::purge($connection);
        app()->forgetInstance(Settings::class);
        Cache::flush();

        throw new RuntimeException('boom');
    })->middleware('web');

    try {
        $this->get('/ar/__test/error-dbless/500')
            ->assertStatus(500)
            ->assertSee('حدث خطأ من جهتنا', false)
            ->assertDontSee('Server Error');
    } finally {
        config(["database.connections.{$connection}.port" => $port]);
        DB::purge($connection);
    }
});
