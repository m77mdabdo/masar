<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Support\EntityUrl;

function publishedArticle(array $attributes = []): Article
{
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);

    return publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => $category->id,
        ...$attributes,
    ]);
}

it('renders a published article', function (): void {
    $article = publishedArticle(['slug' => 'a-live-story']);

    $this->get(app(EntityUrl::class)->for($article, 'ar'))
        ->assertOk()
        ->assertSee($article->title, false);
});

it('404s a draft', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);
    $draft = Article::factory()->draft()->create(['locale' => 'ar', 'slug' => 'not-yet', 'category_id' => $category->id]);

    $this->get("/ar/saudi/{$draft->slug}")->assertNotFound();
});

it('404s a scheduled article before its time', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);
    Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->addDay(),
        'locale' => 'ar',
        'slug' => 'tomorrow',
        'category_id' => $category->id,
    ]);

    // Status says published; the clock says not yet.
    $this->get('/ar/saudi/tomorrow')->assertNotFound();
});

it('404s a soft-deleted article', function (): void {
    $article = publishedArticle(['slug' => 'deleted-story']);
    $article->delete();

    $this->get('/ar/saudi/deleted-story')->assertNotFound();
});

it('404s when the category in the url does not match the article', function (): void {
    publishedArticle(['slug' => 'a-story']);
    Category::factory()->create(['slug' => 'business']);

    // Otherwise the same article would be reachable at two URLs and compete
    // with its own canonical in search results.
    $this->get('/ar/business/a-story')->assertNotFound();
});

it('renders the 30-second summary', function (): void {
    $article = publishedArticle(['slug' => 'with-summary']);

    $this->get("/ar/saudi/{$article->slug}")
        ->assertOk()
        ->assertSee('في 30 ثانية', false)
        ->assertSee('نقطة أولى', false);
});

it('renders the why-it-matters block', function (): void {
    $article = publishedArticle(['slug' => 'why-matters']);

    $this->get("/ar/saudi/{$article->slug}")
        ->assertOk()
        ->assertSee('لماذا يهم هذا؟', false);
});

it('renders sources with the fact-check badge', function (): void {
    $article = publishedArticle(['slug' => 'with-sources']);

    $this->get("/ar/saudi/{$article->slug}")
        ->assertOk()
        ->assertSee('المصادر', false)
        ->assertSee('دُقّقت المعلومات', false);
});

it('emits NewsArticle and BreadcrumbList schema', function (): void {
    $article = publishedArticle(['slug' => 'schema-story']);

    $this->get("/ar/saudi/{$article->slug}")
        ->assertOk()
        ->assertSee('"@type":"NewsArticle"', false)
        ->assertSee('BreadcrumbList', false);
});

it('sets a canonical url matching EntityUrl', function (): void {
    $article = publishedArticle(['slug' => 'canonical-story']);

    $this->get("/ar/saudi/{$article->slug}")
        ->assertOk()
        ->assertSee(app(EntityUrl::class)->for($article, 'ar', true), false);
});

it('honours the noindex flag', function (): void {
    $article = publishedArticle(['slug' => 'hidden-story', 'noindex' => true]);

    $this->get("/ar/saudi/{$article->slug}")
        ->assertOk()
        ->assertSee('name="robots" content="noindex, follow"', false);
});

it('discloses sponsored content to the reader', function (): void {
    $article = publishedArticle([
        'slug' => 'sponsored-story',
        'is_sponsored' => true,
        'sponsor_name' => 'مجموعة الأفق',
    ]);

    $this->get("/ar/saudi/{$article->slug}")
        ->assertOk()
        ->assertSee('محتوى مدفوع', false)
        ->assertSee('مجموعة الأفق', false);
});

it('marks the hero image eager and everything else lazy', function (): void {
    $article = publishedArticle(['slug' => 'image-story']);

    $html = $this->get("/ar/saudi/{$article->slug}")->assertOk()->getContent();

    // The hero is the LCP element; lazy-loading it would delay the metric it
    // defines.
    expect(substr_count($html, 'fetchpriority="high"'))->toBe(1);
});
