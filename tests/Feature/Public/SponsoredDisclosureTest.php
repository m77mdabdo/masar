<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Category;
use Illuminate\Support\Facades\Blade;

/**
 * Paid placement is disclosed to the reader, every time (§5).
 *
 * The label was on the article page and on none of the six card weights, so a
 * sponsored piece sat in a homepage grid or a category list with nothing to
 * distinguish it until a reader clicked through — while `/ar/advertise` told
 * advertisers the label appears "in every place it appears on the site".
 *
 * This is the editorial commitment, not a revenue feature, so it is tested as
 * one: every card weight, and the file-level guarantee that a new card cannot
 * be added without it.
 */
it('discloses a sponsored article on every card weight', function (string $card): void {
    $article = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'is_sponsored' => true,
        'sponsor_name' => 'شركة الرعاية',
    ]);

    $html = Blade::render(
        '<x-article.'.$card.' :article="$article" />',
        ['article' => $article->fresh()],
    );

    expect($html)->toContain('محتوى مدفوع');
})->with(['card-article', 'card-row', 'card-lead', 'card-story', 'card-rank', 'card-overlay']);

it('shows nothing on a card that is not sponsored', function (): void {
    $article = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'is_sponsored' => false,
    ]);

    $html = Blade::render('<x-article.card-article :article="$article" />', ['article' => $article->fresh()]);

    expect($html)->not->toContain('محتوى مدفوع');
});

it('keeps the disclosure on a sponsored article in a real listing', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);

    publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => $category->getKey(),
        'is_sponsored' => true,
        'sponsor_name' => 'شركة الرعاية',
    ]);

    expect($this->get('/ar/saudi')->assertOk()->getContent())->toContain('محتوى مدفوع');
});

it('requires every card weight to carry the disclosure component', function (): void {
    // The rule, not the six edits. A seventh card added without the component
    // fails here rather than shipping an undisclosed placement.
    $missing = [];

    foreach (glob(resource_path('views/components/article/card-*.blade.php')) as $path) {
        $markup = (string) file_get_contents($path);

        if (! str_contains($markup, 'sponsored-flag')) {
            $missing[] = basename($path);
        }
    }

    expect($missing)->toBe([], 'card weights with no sponsored disclosure: '.implode(', ', $missing));
});

it('cannot publish a sponsored article without naming the sponsor', function (): void {
    // The gate rule this disclosure depends on: an unnamed sponsor is not a
    // disclosure at all.
    $article = publishableArticle([
        'status' => ArticleStatus::Writing,
        'is_sponsored' => true,
        'sponsor_name' => null,
    ]);

    expect($article->fresh()->isPublishable())->not->toBeEmpty();
});
