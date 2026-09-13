<?php

declare(strict_types=1);

use App\Actions\Redirects\NormalisePath;
use App\Actions\Redirects\ValidateRedirect;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Redirect;

function validateRedirect(string $from, string $to, ?int $ignoreId = null): array
{
    return app(ValidateRedirect::class)($from, $to, $ignoreId);
}

it('normalises every spelling of a path to one form', function (string $input): void {
    expect(app(NormalisePath::class)($input))->toBe('/ar/saudi');
})->with([
    'ar/saudi',
    '/ar/saudi/',
    '/ar/saudi?utm_source=newsletter',
    '//ar//saudi//',
    'https://masar.sa/ar/saudi',
    '/ar/saudi#section',
]);

it('issues a 301 for a registered redirect', function (): void {
    Redirect::create(['from_path' => '/ar/old-story', 'to_path' => '/ar/new-story', 'status_code' => 301]);

    $this->get('/ar/old-story')
        ->assertStatus(301)
        ->assertRedirect('/ar/new-story');
});

it('issues a 302 when that is what was chosen', function (): void {
    Redirect::create(['from_path' => '/ar/temp', 'to_path' => '/ar/elsewhere', 'status_code' => 302]);

    $this->get('/ar/temp')->assertStatus(302);
});

it('increments the hit counter and stamps the time', function (): void {
    $redirect = Redirect::create(['from_path' => '/ar/old', 'to_path' => '/ar/new', 'status_code' => 301]);

    $this->get('/ar/old');
    $this->get('/ar/old');

    $redirect->refresh();

    expect($redirect->hits)->toBe(2)
        ->and($redirect->last_hit_at)->not->toBeNull();
});

it('matches regardless of trailing slash or query string', function (): void {
    $redirect = Redirect::create(['from_path' => '/ar/old', 'to_path' => '/ar/new', 'status_code' => 301]);

    $this->get('/ar/old/')->assertRedirect('/ar/new');
    $this->get('/ar/old?utm_source=x')->assertRedirect('/ar/new');

    expect($redirect->fresh()->hits)->toBe(2);
});

it('leaves an unmatched 404 alone', function (): void {
    $this->get('/ar/nothing-here')->assertNotFound();
});

it('rejects a redirect that points at itself', function (): void {
    expect(validateRedirect('/ar/a', '/ar/a'))->not->toBeEmpty();
});

it('rejects a direct loop', function (): void {
    Redirect::create(['from_path' => '/ar/a', 'to_path' => '/ar/b', 'status_code' => 301]);

    $problems = validateRedirect('/ar/b', '/ar/a');

    expect($problems)->not->toBeEmpty()
        ->and(implode(' ', $problems))->toContain('حلقة');
});

it('rejects a chain longer than one hop', function (): void {
    Redirect::create(['from_path' => '/ar/b', 'to_path' => '/ar/c', 'status_code' => 301]);

    // A → B where B → C already exists would make the reader take two hops.
    $problems = validateRedirect('/ar/a', '/ar/b');

    expect($problems)->not->toBeEmpty()
        ->and(implode(' ', $problems))->toContain('سلسلة');
});

it('rejects a duplicate source path', function (): void {
    Redirect::create(['from_path' => '/ar/a', 'to_path' => '/ar/b', 'status_code' => 301]);

    expect(validateRedirect('/ar/a', '/ar/z'))->not->toBeEmpty();
});

it('lets a redirect keep its own source path when edited', function (): void {
    $redirect = Redirect::create(['from_path' => '/ar/a', 'to_path' => '/ar/b', 'status_code' => 301]);

    expect(validateRedirect('/ar/a', '/ar/c', $redirect->id))->toBeEmpty();
});

it('rejects a redirect that would shadow a published article', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);
    Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'slug' => 'foreign-property-ownership',
        'category_id' => $category->id,
    ]);

    // Silent content loss: the article stops receiving traffic and nothing errors.
    $problems = validateRedirect('/ar/saudi/foreign-property-ownership', '/ar/elsewhere');

    expect($problems)->not->toBeEmpty()
        ->and(implode(' ', $problems))->toContain('منشورًا');
});

it('rejects a redirect that would shadow a category', function (): void {
    Category::factory()->create(['slug' => 'business', 'is_active' => true]);

    expect(validateRedirect('/ar/business', '/ar/elsewhere'))->not->toBeEmpty();
});

it('allows a redirect from a path whose article is only a draft', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);
    Article::factory()->draft()->create(['locale' => 'ar', 'slug' => 'unpublished', 'category_id' => $category->id]);

    // Nothing live is being hidden, so this is legitimate.
    expect(validateRedirect('/ar/saudi/unpublished', '/ar/elsewhere'))->toBeEmpty();
});

it('refuses to redirect the homepage', function (): void {
    expect(validateRedirect('/', '/ar'))->not->toBeEmpty();
});

it('accepts an ordinary redirect from a dead path', function (): void {
    expect(validateRedirect('/ar/2019/old-url', '/ar/saudi/new-story'))->toBeEmpty();
});
