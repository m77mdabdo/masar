<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\Settings;

/**
 * The masthead and the ticker both degrade to rendering nothing.
 *
 * `masthead` takes `:menu="$headerMenu ?? collect()"` and `ticker` is wrapped in
 * `@if ($ticker)`, so a variable that fails to arrive produces an empty header
 * and no band — no error, no warning, no failing test. That happened: after the
 * development database was wiped and restored, the site served with no main
 * navigation and no ticker while the data sat intact in the tables behind an
 * empty cached snapshot. Nothing caught it for hours.
 *
 * These assert the rendered page, because the database being right is not the
 * same claim.
 */
/** The smallest set of settings that makes the band render. */
function tickerFigures(): void
{
    app(Settings::class)->setMany([
        'market.as_of' => now()->subHours(3)->format('Y-m-d H:i'),
        'market.source' => 'أرقام تجريبية — ليست قراءة سوق',
        'market.ticker' => [
            ['label' => 'تاسي', 'value' => '11,235', 'change' => 0.4, 'series' => '11150,11180,11235'],
            ['label' => 'برنت', 'value' => '82.10', 'change' => -0.3, 'series' => '82.9,82.4,82.1'],
        ],
    ]);
}

it('renders the main navigation in the masthead', function (): void {
    // `key`, not `location`; and the desktop bar filters on show_desktop, so an
    // item that omits it is invisible in exactly the way this test is about.
    $menu = Menu::factory()->create(['key' => 'header']);

    foreach (['السعودية والأسواق', 'الأعمال والشركات', 'رؤى وفرص'] as $i => $label) {
        MenuItem::factory()->create([
            'menu_id' => $menu->getKey(),
            'label' => ['ar' => $label, 'en' => 'Section '.$i],
            'url' => '/ar/saudi',
            'linkable_type' => null,
            'linkable_id' => null,
            'sort_order' => $i + 1,
            'is_active' => true,
            'show_desktop' => true,
            'show_mobile' => true,
        ]);
    }

    $html = $this->get('/ar')->assertOk()->getContent();
    $header = preg_match('/<header\b.*?<\/header>/s', $html, $m) === 1 ? $m[0] : '';

    expect($header)->not->toBe('', 'no <header> rendered');

    foreach (['السعودية والأسواق', 'الأعمال والشركات', 'رؤى وفرص'] as $label) {
        // toContain() takes needles, not a message — a second argument here
        // would quietly assert the header contains the message text too.
        expect(str_contains($header, $label))->toBeTrue("missing from the masthead: {$label}");
    }
});

it('renders the market ticker when the figures carry an as-of date', function (): void {
    // Written directly rather than through MarketFiguresSeeder, which guards
    // `app()->environment('local')` and is a no-op under APP_ENV=testing —
    // correct for a demo seeder, useless for a fixture.
    tickerFigures();

    $html = $this->get('/ar')->assertOk()->getContent();

    // Keyed on the landmark, not on a figure: the numbers are editor-entered
    // and change, the band's role does not.
    expect($html)->toContain('aria-label="مؤشرات السوق"');
});

it('withholds the ticker when there is no as-of date, and says nothing else is wrong', function (): void {
    // The gate working as designed. Distinguishing this from the failure above
    // is the whole point — one is correct, the other is a hidden band.
    tickerFigures();
    app(Settings::class)->set('market.as_of', null);

    $html = $this->get('/ar')->assertOk()->getContent();

    expect($html)->not->toContain('aria-label="مؤشرات السوق"');
});
