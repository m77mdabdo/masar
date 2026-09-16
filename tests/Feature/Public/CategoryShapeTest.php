<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Category;

/**
 * The layout follows the supply, not the other way round.
 *
 * Measured before this rule existed: three of the six categories carried fewer
 * than three featured articles and rendered a three-across grid with holes —
 * saudi with one, business and global with two. `global` is the case that makes
 * it explicit: it is thin because global coverage only runs when it touches the
 * Saudi market, so the answer is a layout that reads deliberately at seven
 * articles, not seven more articles.
 */
function categoryWith(int $featured, int $rest = 4): Category
{
    $category = Category::factory()->create(['slug' => 'saudi']);

    foreach (range(1, $featured + $rest) as $i) {
        publishableArticle([
            'status' => ArticleStatus::Published,
            'published_at' => now()->subHours($i),
            'locale' => 'ar',
            'category_id' => $category->getKey(),
            'is_featured' => $i <= $featured,
        ]);
    }

    return $category;
}

it('gives a single feature the whole width instead of a third of a row', function (): void {
    categoryWith(featured: 1);

    $html = $this->get('/ar/saudi')->assertOk()->getContent();

    // The overlay card is the full-width treatment; the three-across grid is not.
    expect($html)->toContain('مختارات')
        ->and($html)->not->toContain('lg:grid-cols-3');
});

it('uses a two-across grid for two features, so there is no hole', function (): void {
    categoryWith(featured: 2);

    $html = $this->get('/ar/saudi')->assertOk()->getContent();

    expect($html)->not->toContain('lg:grid-cols-3');
});

it('uses the three-across grid only when three features exist', function (): void {
    categoryWith(featured: 3);

    expect($this->get('/ar/saudi')->assertOk()->getContent())->toContain('lg:grid-cols-3');
});

it('omits the featured block entirely when nothing is featured', function (): void {
    categoryWith(featured: 0);

    $html = $this->get('/ar/saudi')->assertOk()->getContent();

    expect($html)->not->toContain('مختارات')
        ->and($html)->toContain('أحدث المواد');
});

it('still prioritises whichever image comes first', function (): void {
    // Whatever the shape, exactly one image is eager — the one the browser will
    // pick as LCP.
    categoryWith(featured: 1);

    preg_match_all('/<img\b[^>]*>/i', $this->get('/ar/saudi')->assertOk()->getContent(), $m);
    $eager = array_filter($m[0], fn (string $t): bool => str_contains($t, 'fetchpriority="high"'));

    expect(count($eager))->toBe(1);
});
