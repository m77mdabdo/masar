<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Support\AspectRatio;
use App\Support\EntityUrl;
use App\Support\MediaConversions;
use Database\Seeders\Support\ImageCatalogue;

/**
 * The imagery rules, as checks rather than as intentions.
 *
 * Everything here is something a reader or a screen reader would notice: a box
 * that resizes when the picture arrives, alt text that reads out a filename, a
 * video that downloads before anyone asked for it.
 */
it('describes every catalogued image in Arabic rather than naming the file', function (): void {
    foreach (ImageCatalogue::entries() as $entry) {
        $stem = pathinfo($entry['file'], PATHINFO_FILENAME);

        // Long enough to be a description: "صورة" is not one.
        expect(mb_strlen($entry['alt']))->toBeGreaterThan(30);

        expect($entry['alt'])
            // Arabic letters, not a transliterated filename.
            ->toMatch('/\p{Arabic}/u')
            ->not->toContain($stem)
            ->not->toContain('.jpg')
            ->not->toContain('pexels')
            ->not->toContain('unsplash');

        expect($entry['credit'])->not->toBeEmpty();
    }
});

it('gives every catalogued image a credit and a subject', function (): void {
    foreach (ImageCatalogue::entries() as $entry) {
        expect(trim($entry['credit']))->not->toBe('')
            ->and($entry['subjects'])->not->toBeEmpty()
            ->and($entry['orientation'])->toBeIn(['landscape', 'portrait']);
    }
});

it('lists no image twice in the catalogue', function (): void {
    $files = array_column(ImageCatalogue::entries(), 'file');

    expect(array_unique($files))->toHaveCount(count($files));
});

it('knows the six crops the design specifies', function (): void {
    // 16:9 hero/featured · 16:8 article inline · 16:10 card thumbs ·
    // 4:5 portrait · 1:1 square · 3:4.1 magazine cover.
    $expected = [
        'hero' => 16 / 9,
        'inline' => 16 / 8,
        'card' => 16 / 10,
        'portrait' => 4 / 5,
        'square' => 1.0,
        'cover' => 3 / 4.1,
    ];

    foreach ($expected as $name => $ratio) {
        $box = AspectRatio::from($name);

        expect($box->width / $box->height)->toBeGreaterThan($ratio - 0.01)
            ->and($box->width / $box->height)->toBeLessThan($ratio + 0.01);
    }
});

it('refuses an unknown crop rather than guessing one', function (): void {
    AspectRatio::from('banner');
})->throws(InvalidArgumentException::class);

it('pairs every conversion width with a jpeg fallback', function (): void {
    // A <picture> whose only source is WebP is not a fallback.
    foreach (array_keys(MediaConversions::widths()) as $name) {
        expect(MediaConversions::names())->toContain($name)
            ->and(MediaConversions::names())->toContain($name.MediaConversions::FALLBACK_SUFFIX);
    }
});

it('keeps the social card out of the srcset', function (): void {
    // `og` is a crop for social scrapers. Offering it as a candidate lets the
    // browser choose a size nobody designed the page around.
    expect(array_keys(MediaConversions::srcsetWidths()))->not->toContain('og');
});

it('reserves a box for every image so nothing shifts when it arrives', function (): void {
    $article = illustratedArticle();

    $html = $this->get(app(EntityUrl::class)->for($article))->assertOk()->getContent();

    preg_match('#<main\b.*</main>#s', $html, $main);
    preg_match_all('/<img\b[^>]*>/', $main[0], $images);

    expect($images[0])->not->toBeEmpty();

    foreach ($images[0] as $tag) {
        expect($tag)->toMatch('/\bwidth="\d+"/')
            ->and($tag)->toMatch('/\bheight="\d+"/');
    }
});

it('keeps the seven card weights structurally distinct', function (): void {
    $article = illustratedArticle();

    // The prototype runs seven weights, and the variation between them is most
    // of what makes a page look edited rather than generated. Collapsing any
    // two is the failure this guards: a page of one card at seven sizes.
    $rendered = collect(['article', 'story', 'lead', 'row', 'rank', 'overlay'])
        ->mapWithKeys(fn (string $weight): array => [
            $weight => (string) view("components.article.card-{$weight}", [
                'article' => $article, 'rank' => 1, 'marker' => null,
            ])->render(),
        ]);

    // Crop is the first thing that separates them.
    expect($rendered['article'])->toContain('ratio-hero')
        ->and($rendered['story'])->toContain('ratio-card')
        ->and($rendered['lead'])->toContain('ratio-square')
        ->and($rendered['row'])->toContain('ratio-card')
        // The overlay card fills its parent instead of carrying a ratio box:
        // its height comes from the content laid over the photograph.
        ->and($rendered['overlay'])->toContain('absolute inset-0')
        ->and($rendered['rank'])->not->toContain('<img');

    // Then structure: only two carry a standfirst, only one inverts the order
    // of text and picture, only one has no picture at all.
    expect($rendered['article'])->toContain('border-t border-line')
        ->and($rendered['lead'])->toContain('grid-cols-[1fr_5.75rem]')
        ->and($rendered['row'])->toContain('grid-cols-[4.875rem_1fr]')
        ->and($rendered['overlay'])->toContain('card-scrim')
        ->and($rendered['rank'])->not->toContain('ratio-');

    // And no two produce the same markup.
    expect($rendered->unique()->count())->toBe($rendered->count());
});

function illustratedArticle(): Article
{
    return Article::factory()->withHeroImage()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => Category::factory()->create(['slug' => 'saudi', 'is_active' => true])->id,
    ]);
}
