<?php

declare(strict_types=1);

use App\Enums\TranslationStatus;
use App\Models\Article;
use App\Models\HomepageLayout;
use App\Support\EntityUrl;

/*
 * hreflang, the switcher and the sitemap answer one question — which editions
 * of this page exist — from App\Support\Alternates. These pin that each says
 * only what is true: an edition is offered for a story only when a published
 * translation exists, and never by pointing a crawler at a front page.
 */
beforeEach(function (): void {
    HomepageLayout::factory()->create(['is_active' => true]);
    config()->set('masar.locales.en.enabled', true);
    config()->set('masar.locales.fr.enabled', true);
});

function translated(Article $source, string $locale, bool $published): Article
{
    $translation = $published ? Article::factory()->published() : Article::factory()->draft();

    return $translation->create([
        'locale' => $locale,
        'translation_group_id' => $source->translation_group_id,
        'source_article_id' => $source->id,
        'translation_status' => TranslationStatus::Reviewed,
        'category_id' => $source->category_id,
        'slug' => $source->slug,
    ]);
}

it('lists a story’s published translations as alternates, and never a draft', function (): void {
    $source = Article::factory()->published()->create();
    $en = translated($source, 'en', true);
    translated($source, 'fr', false);
    $urls = app(EntityUrl::class);

    $html = $this->get($urls->for($source, 'ar'))->assertOk()->getContent();

    expect($html)->toContain('hreflang="ar" href="'.$urls->for($source, 'ar', true).'"')
        ->toContain('hreflang="en" href="'.$urls->for($en, 'en', true).'"')
        ->toContain('hreflang="x-default" href="'.$urls->for($source, 'ar', true).'"')
        ->not->toContain('rel="alternate" hreflang="fr"');
});

it('does not tell a crawler that another edition’s front page is this story', function (): void {
    $source = Article::factory()->published()->create();

    $html = $this->get(app(EntityUrl::class)->for($source, 'ar'))->getContent();

    // <a hreflang> in the switcher is expected; <link rel="alternate"> is the claim.
    expect($html)->not->toContain('rel="alternate" hreflang="en"')->not->toContain('rel="alternate" hreflang="fr"');
});

it('offers the same structural page in every enabled edition', function (): void {
    $html = $this->get('/ar/about')->assertOk()->getContent();

    expect($html)->toContain('hreflang="en" href="'.route('web.about', 'en').'"')
        ->toContain('hreflang="fr" href="'.route('web.about', 'fr').'"')
        ->toContain('<meta property="og:locale" content="ar_SA">')
        ->toContain('<meta property="og:locale:alternate" content="en_US">');
});

it('switches to the same story in another edition, or says it will land on the front page', function (): void {
    $source = Article::factory()->published()->create();
    $en = translated($source, 'en', true);
    $urls = app(EntityUrl::class);

    $html = $this->get($urls->for($source, 'ar'))->getContent();

    // English has this story: the switcher goes to it.
    expect($html)->toContain('href="'.$urls->for($en, 'en', true).'" hreflang="en" lang="en" dir="ltr"')
        // French does not: the switcher goes to the French front page and says so, in French.
        ->toContain('href="'.route('web.home', 'fr').'" hreflang="fr" lang="fr" dir="ltr"')
        ->toContain('Non disponible en français — page d’accueil')
        ->toContain('aria-current="true"');
});

it('hides the switcher while a single edition is live', function (): void {
    foreach (['en', 'fr', 'hi', 'ur-pk', 'zh-hans'] as $code) {
        config()->set("masar.locales.{$code}.enabled", false);
    }

    expect($this->get('/ar')->getContent())->not->toContain('aria-controls="language-switcher"');
});

it('remembers the chosen edition for the bare root', function (): void {
    $this->get('/en')->assertOk()->assertCookie(\App\Http\Middleware\SetLocale::COOKIE, 'en');

    $this->withCookie(\App\Http\Middleware\SetLocale::COOKIE, 'fr')
        ->get('/', ['Accept-Language' => 'ar'])
        ->assertRedirect(route('web.home', 'fr'));
});

it('gives the sitemap reciprocal hreflang sets', function (): void {
    $source = Article::factory()->published()->create();
    $en = translated($source, 'en', true);
    $urls = app(EntityUrl::class);
    // Demo mode hides the sitemap; this is the published site's sitemap.
    app(\App\Support\Settings::class)->set(\App\Support\DemoMode::SETTING, false);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    $ar = $urls->for($source, 'ar', true);
    $enUrl = $urls->for($en, 'en', true);

    // Both versions list both — Google ignores a set that is not reciprocal.
    expect(substr_count($xml, 'hreflang="en" href="'.$enUrl.'"'))->toBe(2)
        ->and(substr_count($xml, 'hreflang="ar" href="'.$ar.'"'))->toBe(2);
});
