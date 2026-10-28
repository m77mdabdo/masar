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

// ---- Six editions (owner's ruling, 2026-10-28) ------------------------------

it('registers all six editions with a distinct URL code and BCP 47 tag', function (): void {
    expect(\App\Support\Locales::codes())->toBe(['ar', 'en', 'fr', 'hi', 'ur-pk', 'zh-hans'])
        ->and(array_map(\App\Support\Locales::tag(...), \App\Support\Locales::codes()))
        ->toBe(['ar', 'en', 'fr', 'hi', 'ur-PK', 'zh-Hans']);
});

it('runs Arabic and Urdu right to left and the other four left to right', function (): void {
    $rtl = array_values(array_filter(\App\Support\Locales::codes(), fn (string $c): bool => \App\Support\Locales::dir($c) === 'rtl'));

    expect($rtl)->toBe(['ar', 'ur-pk']);
});

it('sets Urdu in Nastaliq, not in the Arabic naskh face', function (): void {
    // Same Unicode block, a different writing tradition. Hindi is not a
    // substitute for Urdu either: a different script entirely.
    expect(\App\Support\Locales::script('ur-pk'))->toBe('nastaliq')
        ->and(\App\Support\Locales::script('ar'))->toBe('arabic')
        ->and(\App\Support\Locales::script('hi'))->toBe('devanagari');
});

it('maps any spelling of a browser tag back to the URL code', function (string $tag, ?string $code): void {
    expect(\App\Support\Locales::fromTag($tag))->toBe($code);
})->with([
    ['ur_PK', 'ur-pk'], ['UR-pk', 'ur-pk'], ['zh-Hans', 'zh-hans'], ['zh_Hans', 'zh-hans'], ['fr', 'fr'], ['de', null],
]);

it('serves an enabled multi-part edition under its lowercase segment, with the BCP 47 tag in <html lang>', function (): void {
    config()->set('masar.locales.ur-pk.enabled', true);

    $this->get('/ur-pk')
        ->assertOk()
        ->assertSee('<html lang="ur-PK" dir="rtl" data-script="nastaliq">', false);
});

it('negotiates the root redirect on BCP 47 tags', function (): void {
    config()->set('masar.locales.zh-hans.enabled', true);

    $this->get('/', ['Accept-Language' => 'zh-Hans,zh;q=0.9'])
        ->assertRedirect(route('web.home', 'zh-hans'));
});

it('stores a locale longer than two characters', function (): void {
    $article = \App\Models\Article::factory()->create(['locale' => 'zh-hans']);

    expect($article->fresh()->locale)->toBe('zh-hans');
});
