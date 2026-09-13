<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;

function articleWithVideo(string $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'): Article
{
    $article = Article::factory()->withHeroImage()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => Category::factory()->create(['slug' => 'saudi', 'is_active' => true])->id,
    ]);

    $article->blocks()->create([
        'type' => 'video',
        'data' => ['url' => $url, 'title' => 'تقرير مصور'],
        'sort_order' => 0,
    ]);

    return $article->refresh();
}

/**
 * A YouTube embed loads roughly half a megabyte of script and sets cookies
 * before a reader has decided to watch anything. The facade defers all of it
 * behind a click.
 */
it('loads no youtube iframe before a click', function (): void {
    articleWithVideo();

    $html = $this->get('/ar/video')->assertOk()->getContent();

    // The iframe lives inside an Alpine <template>, which the browser does not
    // fetch until the template is instantiated.
    expect($html)->not->toContain('<iframe src="https://www.youtube')
        ->and($html)->not->toContain('youtube.com/iframe_api');
});

it('requests no script from a third party on load', function (): void {
    articleWithVideo();

    $html = $this->get('/ar/video')->assertOk()->getContent();

    preg_match_all('/<script[^>]+src="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $src) {
        expect($src)->not->toContain('youtube')
            ->and($src)->not->toContain('ytimg')
            ->and($src)->not->toContain('googletagmanager');
    }
});

it('shows a poster and a play control instead', function (): void {
    articleWithVideo();

    $html = $this->get('/ar/video')->assertOk()->getContent();

    expect($html)->toContain('aria-label="تشغيل');

    // The poster is ours. i.ytimg.com sets no cookies, but the rule is no
    // third-party request before a click, and a thumbnail fetched from Google
    // on page load is one.
    preg_match('/<img[^>]+src="([^"]+)"[^>]*class="[^"]*absolute inset-0/', $html, $poster);

    expect($poster)->not->toBeEmpty()
        ->and($poster[1])->not->toContain('ytimg')
        ->and($poster[1])->not->toContain('youtube');
});

it('fetches nothing at all from a third party before a click', function (): void {
    articleWithVideo();

    $html = $this->get('/ar/video')->assertOk()->getContent();

    // Every URL the browser would fetch without being asked: scripts, styles,
    // images, preloads. The deferred iframe is excluded because it lives inside
    // an Alpine template and is never requested until one is instantiated.
    $outsideTemplates = (string) preg_replace('#<template\b.*?</template>#s', '', $html);

    preg_match_all(
        '/(?:src|href)="(https?:\/\/[^"]+)"/',
        $outsideTemplates,
        $matches
    );

    $thirdParty = array_values(array_filter(
        $matches[1],
        fn (string $url): bool => ! str_starts_with($url, config('app.url'))
    ));

    expect($thirdParty)->toBe([]);
});

it('builds the embed only inside a click-gated template', function (): void {
    articleWithVideo();

    $html = $this->get('/ar/video')->assertOk()->getContent();

    // The URL is present as markup, but only within x-if="loaded".
    expect($html)->toContain('x-if="loaded"')
        ->and($html)->toContain('youtube-nocookie.com');
});

it('uses the no-cookie host for the deferred embed', function (): void {
    articleWithVideo();

    $html = $this->get('/ar/video')->assertOk()->getContent();

    expect($html)->toContain('youtube-nocookie.com')
        ->and($html)->not->toContain('www.youtube.com/embed');
});

it('extracts the video id from every common youtube url shape', function (string $url): void {
    articleWithVideo($url);

    $this->get('/ar/video')->assertOk()->assertSee('dQw4w9WgXcQ', false);
})->with([
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://youtu.be/dQw4w9WgXcQ',
    'https://www.youtube.com/embed/dQw4w9WgXcQ',
    'https://www.youtube.com/shorts/dQw4w9WgXcQ',
]);

it('renders nothing for an unparseable url rather than a broken frame', function (): void {
    articleWithVideo('https://example.com/not-a-video');

    $this->get('/ar/video')->assertOk()->assertDontSee('youtube-nocookie', false);
});

it('keeps the facade box from shifting the layout', function (): void {
    articleWithVideo();

    // A reserved aspect-ratio box is most of the CLS budget on this page.
    $this->get('/ar/video')->assertOk()->assertSee('aspect-ratio: 16 / 9', false);
});
