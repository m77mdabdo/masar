<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;

/**
 * Budgets, enforced by the build rather than by good intentions.
 *
 * Page weight only ever grows: a component here, an embed there, none of them
 * individually unreasonable. These fail the moment the article page crosses a
 * line, which is the only point at which the cause is still obvious.
 */
const JS_BUDGET_BYTES = 40 * 1024;
const TRANSFER_BUDGET_BYTES = 250 * 1024;

/**
 * @return array<string, int> built asset path => gzipped size
 */
function builtAssets(string $extension): array
{
    $manifest = public_path('build/manifest.json');

    expect(file_exists($manifest))->toBeTrue('run `npm run build` before the budget tests');

    $sizes = [];

    foreach (glob(public_path("build/assets/*.{$extension}")) as $file) {
        $sizes[basename($file)] = strlen((string) gzencode((string) file_get_contents($file), 6));
    }

    return $sizes;
}

it('keeps compressed javascript under budget', function (): void {
    $total = array_sum(builtAssets('js'));

    expect($total)->toBeLessThanOrEqual(
        JS_BUDGET_BYTES,
        sprintf('JS is %.1f KB gzipped, budget is %d KB', $total / 1024, JS_BUDGET_BYTES / 1024),
    );
});

it('keeps the article page under the transfer budget excluding images', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);

    $article = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'slug' => 'budget-check',
        'category_id' => $category->id,
    ]);

    $html = $this->get("/ar/saudi/{$article->slug}")->assertOk()->getContent();

    // Compressed, because that is what a reader actually downloads — and what
    // the edge is required to send.
    $documentBytes = strlen((string) gzencode($html, 6));

    // The public site loads only the app bundle; the Filament theme is never
    // served to a reader.
    $assetBytes = array_sum(builtAssets('js'))
        + array_sum(array_filter(
            builtAssets('css'),
            fn (string $name): bool => ! str_starts_with($name, 'theme-'),
            ARRAY_FILTER_USE_KEY,
        ));

    $fontBytes = array_sum(array_map(
        fn (string $file): int => (int) filesize($file),
        // Only the faces a first paint actually needs: the preloaded pair.
        glob(public_path('fonts/{readex-pro-600-arabic,ibm-plex-sans-arabic-400-arabic}.woff2'), GLOB_BRACE) ?: [],
    ));

    $total = $documentBytes + $assetBytes + $fontBytes;

    expect($total)->toBeLessThanOrEqual(
        TRANSFER_BUDGET_BYTES,
        sprintf(
            'article transfer is %.1f KB (document %.1f + assets %.1f + fonts %.1f), budget %d KB',
            $total / 1024, $documentBytes / 1024, $assetBytes / 1024, $fontBytes / 1024,
            TRANSFER_BUDGET_BYTES / 1024,
        ),
    );
});

it('ships only the font weights templates can reach', function (): void {
    $css = (string) file_get_contents(resource_path('css/masar-fonts.css'));

    // Faces that fetch a file. A metric-matched fallback is declared with
    // `local()` only and deliberately ships nothing — it exists to make the
    // fallback occupy the space the real face will, so a swap changes the
    // shapes and not the layout.
    $withFile = preg_match_all('/@font-face\s*\{[^}]*url\(/s', $css);
    $localOnly = preg_match_all('/@font-face\s*\{(?:(?!url\()[^}])*\}/s', $css);
    $files = count(glob(public_path('fonts/*.woff2')));

    // An unused @font-face that fetches is a file the browser may still pull
    // when a glyph happens to match its unicode-range.
    expect($withFile)->toBe($files);

    // And a local-only face without size-adjust is pointless: it would be a
    // second name for the same fallback.
    if ($localOnly > 0) {
        expect($css)->toContain('size-adjust:');
    }
});

it('declares no font weight the templates never use', function (): void {
    $css = (string) file_get_contents(resource_path('css/masar-fonts.css'));

    // `font-bold` appears nowhere in the views, so a 700 face is dead weight.
    expect($css)->not->toContain('font-weight: 700');
});

it('loads exactly one eagerly-fetched image per page', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);
    $article = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'slug' => 'one-hero',
        'category_id' => $category->id,
    ]);

    // The hero is the LCP element; eager-loading a second image competes with it.
    expect(substr_count($this->get("/ar/saudi/{$article->slug}")->getContent(), 'fetchpriority="high"'))->toBe(1);
});

it('lazy-loads every image below the fold', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);

    // A listing page is where lazy loading actually matters — one article alone
    // has nothing below the hero to defer.
    Article::factory()->count(6)->withHeroImage()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => $category->id,
    ]);

    $html = $this->get('/ar/saudi')->assertOk()->getContent();

    expect(substr_count($html, 'loading="lazy"'))->toBeGreaterThan(0)
        ->and(substr_count($html, 'fetchpriority="high"'))->toBe(0);
});
