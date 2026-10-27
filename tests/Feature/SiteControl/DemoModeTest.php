<?php

declare(strict_types=1);

use App\Support\DemoMode;
use App\Support\Settings;

/**
 * The switch that keeps AI-generated demo content out of Google under this
 * brand. Indexing it is not a bug you fix later — it attaches to the masthead
 * before a real word is published.
 *
 * So the switch defaults closed and one setting drives five surfaces.
 */
beforeEach(function (): void {
    app(Settings::class)->flush();
});

it('defaults to closed when nobody has decided', function (): void {
    // A fresh install has no settings row. Closed is the safe reading of that.
    app(Settings::class)->set(DemoMode::SETTING, null);

    expect(app(DemoMode::class)->isOn())->toBeTrue();
});

it('emits noindex, nofollow site-wide while on', function (): void {
    app(Settings::class)->set(DemoMode::SETTING, true);

    $html = $this->get('/ar')->assertOk()->getContent();

    expect($html)->toContain('<meta name="robots" content="noindex, nofollow">');
});

it('disallows every crawler in robots.txt while on', function (): void {
    app(Settings::class)->set(DemoMode::SETTING, true);

    $body = $this->get('/robots.txt')->assertOk()->getContent();

    expect($body)->toContain('Disallow: /')
        // And advertises no sitemap: handing a crawler the list while telling
        // it to stay out resolves in the crawler's favour often enough.
        ->and($body)->not->toContain('Sitemap:');
});

it('serves no sitemap at all while on', function (): void {
    app(Settings::class)->set(DemoMode::SETTING, true);

    $this->get('/sitemap.xml')->assertNotFound();
    $this->get('/news-sitemap.xml')->assertNotFound();
});

it('shows the reader a banner while on', function (): void {
    // Visible to a person, not only to a crawler.
    app(Settings::class)->set(DemoMode::SETTING, true);

    expect($this->get('/ar')->assertOk()->getContent())->toContain('نسخة تجريبية');
});

it('opens all five surfaces when switched off', function (): void {
    app(Settings::class)->set(DemoMode::SETTING, false);

    $home = $this->get('/ar')->assertOk()->getContent();
    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect($home)->not->toContain('noindex, nofollow')
        ->and($home)->not->toContain('نسخة تجريبية')
        ->and($robots)->not->toContain('Disallow: /')
        ->and($robots)->toContain('Sitemap:');

    $this->get('/sitemap.xml')->assertOk();
    $this->get('/ar')->assertHeaderMissing('X-Robots-Tag');
});

it('sends X-Robots-Tag on every response type while on, not only HTML', function (string $path): void {
    // The meta tag reaches HTML only. The feeds carry every demo article's
    // text, and a 404 is a response a crawler sees too.
    app(Settings::class)->set(DemoMode::SETTING, true);

    $this->get($path)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with(['/ar', '/ar/rss.xml', '/rss.xml', '/robots.txt', '/ar/no-such-page-anywhere']);

it('has no static robots.txt shadowing the route', function (): void {
    // A static public/robots.txt silently wins over the route under any server
    // that tries files first — `artisan serve` and nginx `try_files` both do.
    // One shipped with the Laravel skeleton and had been winning all along, so
    // the editable setting, the sitemap reference and demo mode were all
    // unreachable in every real environment while this test's HTTP siblings
    // passed: the test client never touches the filesystem.
    expect(file_exists(public_path('robots.txt')))
        ->toBeFalse('public/robots.txt shadows the robots route — delete it');
});
