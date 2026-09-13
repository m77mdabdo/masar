<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Market;
use App\Models\Topic;
use App\Models\User;

function livePage(array $attributes = []): Article
{
    return Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        ...$attributes,
    ]);
}

it('renders the markets page with curated coverage', function (): void {
    Market::factory()->count(3)->create();
    livePage();

    $this->get('/ar/markets')
        ->assertOk()
        ->assertSee('الأسواق', false)
        // The standing ruling: reading the market, not a quote board.
        ->assertSee('دون عرض أسعار لحظية', false);
});

it('renders the video page', function (): void {
    $article = livePage();
    $article->blocks()->create([
        'type' => 'video',
        'data' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'تقرير'],
        'sort_order' => 0,
    ]);

    $this->get('/ar/video')->assertOk()->assertSee('مرئيات', false);
});

it('renders an author page with Person schema', function (): void {
    $author = User::factory()->create(['name' => 'سارة المطيري']);
    $article = livePage(['author_id' => $author->id]);

    // knowsAbout is built from the topics they actually cover, and omitted when
    // there are none — so the fixture has to give them one.
    $article->topics()->attach(Topic::factory()->create());

    $this->get("/ar/authors/{$author->id}")
        ->assertOk()
        ->assertSee('سارة المطيري', false)
        ->assertSee('"@type":"Person"', false)
        ->assertSee('knowsAbout', false);
});

it('renders an issue page for a featured topic', function (): void {
    $topic = Topic::factory()->featured()->create(['slug' => 'vision-2030']);
    $article = livePage();
    $article->topics()->attach($topic);

    $this->get('/ar/issues/vision-2030')
        ->assertOk()
        ->assertSee('ملف العدد', false);
});

it('404s an issue for a topic that is not featured', function (): void {
    Topic::factory()->create(['slug' => 'ordinary', 'is_featured' => false]);

    $this->get('/ar/issues/ordinary')->assertNotFound();
});

it('404s an unknown author, topic, company and opportunity', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with([
    '/ar/authors/999999',
    '/ar/topics/nope',
    '/ar/companies/nope',
    '/ar/people/nope',
    '/ar/opportunities/nope',
    '/ar/issues/nope',
]);

it('suggests topics rather than dead-ending an empty search', function (): void {
    $topic = Topic::factory()->create(['articles_count' => 5]);

    $this->get('/ar/search?q='.rawurlencode('لاشيءمطابق'))
        ->assertOk()
        ->assertSee('لا نتائج', false)
        ->assertSee($topic->name, false);
});

it('offers topics before any search has been made', function (): void {
    $topic = Topic::factory()->create(['articles_count' => 3]);

    $this->get('/ar/search')->assertOk()->assertSee($topic->name, false);
});

it('keeps search out of the index', function (): void {
    // A search results page is infinite and thin; it should never be indexed.
    $this->get('/ar/search')->assertOk()->assertSee('noindex', false);
});

it('renders the newsletter page with an archive', function (): void {
    Topic::factory()->featured()->create(['articles_count' => 4]);
    livePage();

    $this->get('/ar/newsletter')->assertOk()->assertSee('اشترك', false);
});

it('renders editorial standards with real substance', function (): void {
    $this->get('/ar/editorial-standards')
        ->assertOk()
        ->assertSee('التحقق من المعلومات', false)
        ->assertSee('التصحيح', false)
        ->assertSee('الذكاء الاصطناعي', false)
        ->assertSee('BreadcrumbList', false);
});

it('renders about and contact', function (): void {
    $this->get('/ar/about')->assertOk()->assertSee('عن مسار', false);
    $this->get('/ar/contact')->assertOk()->assertSee('التحرير', false);
});

it('renders a category page with featured and list sections', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);
    livePage(['category_id' => $category->id, 'is_featured' => true]);

    $this->get('/ar/saudi')->assertOk()->assertSee('أحدث المواد', false);
});
