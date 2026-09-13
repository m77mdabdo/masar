<?php

declare(strict_types=1);

use App\Models\HomepageLayout;

beforeEach(function (): void {
    HomepageLayout::factory()->create(['is_active' => true]);
});

it('serves the enabled locale', function (): void {
    $this->get('/ar')->assertOk();
});

it('404s an unknown locale', function (): void {
    // The segment is user input; serving Arabic under /xx would be indexed.
    $this->get('/xx')->assertNotFound();
    $this->get('/zz/saudi')->assertNotFound();
});

it('404s a locale that exists but is disabled', function (): void {
    // English is architected from day one and has no content — an empty English
    // site is worse than none.
    expect(config('masar.locales.en.enabled'))->toBeFalse();

    $this->get('/en')->assertNotFound();
});

it('redirects the bare root to a locale', function (): void {
    $this->get('/')->assertRedirect(route('web.home', 'ar'));
});

it('honours Accept-Language among enabled locales only', function (): void {
    // English is disabled, so asking for it still lands on Arabic.
    $this->get('/', ['Accept-Language' => 'en-GB,en;q=0.9'])
        ->assertRedirect(route('web.home', 'ar'));
});

it('sets the application locale from the segment', function (): void {
    $this->get('/ar')->assertOk();

    expect(app()->getLocale())->toBe('ar');
});

it('renders the html direction from config', function (): void {
    $this->get('/ar')
        ->assertOk()
        ->assertSee('dir="rtl"', false);
});

it('emits hreflang for enabled locales plus x-default', function (): void {
    $this->get('/ar')
        ->assertOk()
        ->assertSee('hreflang="ar"', false)
        ->assertSee('hreflang="x-default"', false);
});
