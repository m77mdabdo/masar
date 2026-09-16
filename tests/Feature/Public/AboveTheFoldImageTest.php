<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Enums\ContentType;
use App\Models\Category;

/**
 * A listing page's own LCP element must not be lazy-loaded.
 *
 * Every card image was `loading="lazy"`, including the first one on the page,
 * which is what the browser eventually picks as LCP. Lighthouse measured the
 * cost on the category page as 2,521ms of load delay — the browser waiting on
 * layout before it even asked for a file that then took 114ms to arrive. Only
 * `big-story` and the article hero passed `eager`, which is why the homepage
 * and article pages were fast and every listing page was not.
 *
 * The failure mode is silence: a card that reverts to lazy renders correctly,
 * looks right in a screenshot, and passes every other test.
 */
function listingWithImages(): Category
{
    $category = Category::factory()->create(['slug' => 'saudi']);

    foreach (range(1, 4) as $i) {
        publishableArticle([
            'status' => ArticleStatus::Published,
            'published_at' => now()->subHours($i),
            'locale' => 'ar',
            'category_id' => $category->getKey(),
            // The featured grid is what carries the full-size crops, and the
            // first of those is the page's LCP element. A category with no
            // featured articles renders 78px row thumbnails instead, which are
            // not an LCP bottleneck and are deliberately left lazy.
            'is_featured' => $i <= 2,
        ]);
    }

    return $category;
}

/** @return array<int, string> */
function imageTags(string $html): array
{
    preg_match_all('/<img\b[^>]*>/i', $html, $m);

    return $m[0];
}

it('loads the first card image eagerly on the category page', function (): void {
    listingWithImages();

    $images = imageTags($this->get('/ar/saudi')->assertOk()->getContent());

    expect($images)->not->toBeEmpty()
        ->and($images[0])->toContain('fetchpriority="high"')
        ->and($images[0])->not->toContain('loading="lazy"');
});

it('keeps the rest of the listing lazy', function (): void {
    // The fix is the first card only. Making every card eager would put a
    // dozen images in front of the one that decides LCP.
    listingWithImages();

    $images = imageTags($this->get('/ar/saudi')->assertOk()->getContent());
    $prioritised = array_filter($images, fn (string $t): bool => str_contains($t, 'fetchpriority="high"'));

    expect(count($images))->toBeGreaterThan(1)
        ->and(count($prioritised))->toBe(1)
        ->and($images[1])->toContain('loading="lazy"');
});

it('prioritises the lead image on the markets page', function (): void {
    // The markets lead is the most recent analysis piece, not merely the most
    // recent article — same root cause as the category page, different query.
    publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subMinutes(5),
        'locale' => 'ar',
        'content_type' => ContentType::Analysis,
    ]);

    $images = imageTags($this->get('/ar/markets')->assertOk()->getContent());

    expect($images)->not->toBeEmpty()
        ->and($images[0])->toContain('fetchpriority="high"');
});
