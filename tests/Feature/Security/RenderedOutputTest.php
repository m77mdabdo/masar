<?php

declare(strict_types=1);

use App\Models\Article;
use App\Support\EntityUrl;
use App\Support\JsonLd;
use App\Support\SafeUrl;

/**
 * Two escapes the rest of the suite cannot see, because both failures produce a
 * page that renders and a test that passes.
 */
function publicUrl(Article $article): string
{
    return app(EntityUrl::class)->for($article->load('category'));
}

it('cannot be broken out of a JSON-LD block by a headline', function (): void {
    $json = JsonLd::encode([
        '@type' => 'NewsArticle',
        'headline' => '</script><img src=x onerror=alert(1)>',
    ]);

    expect($json)->not->toContain('</script>')
        ->and($json)->not->toContain('<img')
        // Present, and present as an escape rather than as a character.
        ->and($json)->toContain('\u003C');
});

it('keeps Arabic readable in JSON-LD rather than escaping it to codepoints', function (): void {
    expect(JsonLd::encode(['headline' => 'الرياض']))->toContain('الرياض');
});

it('puts a hostile headline on the page without a working breakout', function (): void {
    $article = publishableArticle([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'title' => '</script><img src=x onerror=alert(1)>',
    ]);

    $html = $this->get(publicUrl($article))->assertOk()->getContent();

    // This literal sequence is what ends the script element and starts one the
    // browser will run. Its absence is the whole assertion.
    expect($html)->not->toContain('</script><img src=x onerror=alert(1)>');
});

dataset('dangerous urls', [
    'javascript' => ['javascript:alert(1)'],
    'javascript uppercase' => ['JaVaScRiPt:alert(1)'],
    'data html' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
    'vbscript' => ['vbscript:msgbox(1)'],
    'file' => ['file:///etc/passwd'],
    'protocol relative' => ['//evil.example.com'],
    'empty' => [''],
]);

it('refuses to link a url that is not http or https', function (string $url): void {
    expect(SafeUrl::external($url))->toBeNull();
})->with('dangerous urls');

it('passes an ordinary link through untouched', function (): void {
    expect(SafeUrl::external('https://www.spa.gov.sa/ar/N123'))->toBe('https://www.spa.gov.sa/ar/N123')
        ->and(SafeUrl::external('http://example.com/a?b=c#d'))->toBe('http://example.com/a?b=c#d');
});

it('renders a hostile source url as text rather than as a live link', function (): void {
    $article = publishableArticle(['status' => 'published', 'published_at' => now()->subDay()]);

    $article->sources()->update(['url' => 'javascript:alert(1)', 'title' => 'مصدر مشبوه']);

    $html = $this->get(publicUrl($article->fresh()))->assertOk()->getContent();

    expect($html)->toContain('مصدر مشبوه')
        ->and($html)->not->toContain('href="javascript:');
});
