<?php

declare(strict_types=1);

use App\Models\HomepageLayout;
use App\Support\SiteCookies;

/*
 * The legal pages say only what the code does. The cookie policy is held to
 * the cookies a real response sets; the privacy page to the configured
 * dedupe window; the demo notice to demo mode.
 */
beforeEach(fn () => HomepageLayout::factory()->create(['is_active' => true]));

it('serves all three legal pages in all six editions', function (string $locale, string $page): void {
    $this->get("/{$locale}/{$page}")->assertOk()->assertSee(__("pages.legal.{$page}.title", [], $locale), false);
})->with(['ar', 'en', 'fr', 'hi', 'ur-pk', 'zh-hans'])->with(['privacy', 'terms', 'cookies']);

it('lists exactly the cookies the site sets, no more and no fewer', function (): void {
    $set = collect($this->get('/ar/about')->headers->getCookies())->map->getName()->unique()->sort()->values()->all();
    $listed = collect(SiteCookies::all())->pluck('name')->sort()->values()->all();

    expect($listed)->toBe($set);

    $html = $this->get('/en/cookies')->getContent();
    foreach ($listed as $name) {
        expect($html)->toContain('>'.$name.'</code>');
    }
});

it('states the visitor cookie’s real lifetime and the real counting window', function (): void {
    expect($this->get('/en/cookies')->getContent())->toContain('400 days');

    config()->set('masar.views.dedupe_hours', 12);
    expect($this->get('/en/privacy')->getContent())->toContain('within 12 hours');
});

it('carries the demonstration notice in the terms only while the demo is on', function (): void {
    app(\App\Support\Settings::class)->set(\App\Support\DemoMode::SETTING, true);
    $this->get('/en/terms')->assertSee(__('pages.legal.terms.demo_body', [], 'en'), false);

    app(\App\Support\Settings::class)->set(\App\Support\DemoMode::SETTING, false);
    $this->get('/en/terms')->assertDontSee(__('pages.legal.terms.demo_body', [], 'en'), false);
});

it('claims no certification, compliance or retention period nobody set', function (): void {
    foreach (['privacy', 'terms', 'cookies'] as $page) {
        $text = strip_tags($this->get("/en/{$page}")->getContent());
        expect($text)->not->toMatch('/\b(GDPR|PDPL|certified|compliant|ISO ?27001|retain(ed)? for \d)/i');
    }
});
