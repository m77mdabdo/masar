<?php

declare(strict_types=1);

/*
 * The panel is in the signed-in staff member's language, and its direction
 * follows from Filament's own translation for that language. A user with no
 * choice gets the default edition's (Arabic, RTL), as the panel always had.
 */
it('renders the panel in the staff member’s language and direction', function (?string $locale, string $lang, string $dir): void {
    // A writer: publish rights would send the request to 2FA setup first.
    $user = staff('writer');
    $user->forceFill(['locale' => $locale])->save();

    $html = $this->actingAs($user)->get(filament()->getUrl())->assertOk()->getContent();

    expect($html)->toMatch('/<html[^>]*lang="'.preg_quote($lang, '/').'"/')
        ->toMatch('/<html[^>]*dir="'.$dir.'"/');
})->with([
    [null, 'ar', 'rtl'],
    ['en', 'en', 'ltr'],
    ['fr', 'fr', 'ltr'],
    ['ur-pk', 'ur-pk', 'rtl'],
    ['zh-hans', 'zh-hans', 'ltr'],
]);

it('finds Filament’s own strings under our edition codes, and never remaps ours', function (): void {
    app()->setLocale('zh-hans');
    expect(__('filament-panels::layout.direction'))->toBe('ltr');

    app()->setLocale('ur-pk');
    expect(__('filament-panels::layout.direction'))->toBe('rtl')
        // Our own Urdu comes only from lang/ur-pk.
        ->and(__('site.nav.menu'))->toBe('مینو');
});
