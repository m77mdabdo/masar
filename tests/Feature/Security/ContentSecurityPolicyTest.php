<?php

declare(strict_types=1);

use App\Models\HomepageLayout;

/*
 * The public CSP: present on every public HTML page, a fresh nonce on each
 * response, carried by every executable script, and no 'unsafe-inline' for
 * scripts or style elements.
 */
beforeEach(fn () => HomepageLayout::factory()->create(['is_active' => true]));

function csp(\Illuminate\Testing\TestResponse $r): string
{
    return (string) $r->headers->get('Content-Security-Policy');
}

it('sends a policy on every public edition', function (string $path): void {
    $policy = csp($this->get($path)->assertOk());

    expect($policy)->toContain("default-src 'self'")->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'")->toContain("base-uri 'self'")->toContain("form-action 'self'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/");

    // Never inline script, never inline style elements.
    preg_match('/script-src ([^;]+)/', $policy, $script);
    preg_match('/style-src ([^;]+)/', $policy, $style);
    expect($script[1])->not->toContain("'unsafe-inline'")
        ->and($style[1])->not->toContain("'unsafe-inline'");
})->with(['/ar', '/en/about', '/ur-pk/editorial-standards', '/zh-hans/search?q=x']);

it('puts the response’s nonce on every executable inline script and Vite tag', function (): void {
    $response = $this->get('/ar');
    preg_match("/'nonce-([^']+)'/", csp($response), $m);
    $html = $response->getContent();

    preg_match_all('/<script(?![^>]*type="application\/ld\+json")[^>]*>/', $html, $scripts);
    expect($scripts[0])->not->toBeEmpty();
    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain('nonce="'.$m[1].'"');
    }
});

it('uses a fresh nonce on each response', function (): void {
    preg_match("/'nonce-([^']+)'/", csp($this->get('/ar')), $a);
    preg_match("/'nonce-([^']+)'/", csp($this->get('/ar')), $b);

    expect($a[1])->not->toBe($b[1]);
});
