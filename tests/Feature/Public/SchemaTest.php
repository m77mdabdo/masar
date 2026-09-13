<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;

/**
 * Structured data is how a news site appears in Top Stories at all, so a typo in
 * a JSON-LD block is expensive and invisible. These parse the emitted blocks
 * rather than string-matching them.
 *
 * @return array<int, array<string, mixed>>
 */
function schemaBlocks(string $path): array
{
    $html = test()->get($path)->assertOk()->getContent();

    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(function (string $json): array {
        $decoded = json_decode($json, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE, 'JSON-LD did not parse: '.substr($json, 0, 120));

        return $decoded;
    }, $matches[1]);
}

function schemaOfType(string $path, string $type): ?array
{
    foreach (schemaBlocks($path) as $block) {
        if (($block['@type'] ?? null) === $type) {
            return $block;
        }
    }

    return null;
}

function schemaArticle(): Article
{
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);

    return publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'slug' => 'schema-article',
        'category_id' => $category->id,
    ]);
}

it('emits valid json-ld on the homepage', function (): void {
    HomepageLayout::factory()->create(['is_active' => true]);

    expect(schemaBlocks('/ar'))->not->toBeEmpty();
});

it('describes the site with a search action', function (): void {
    HomepageLayout::factory()->create(['is_active' => true]);

    $website = schemaOfType('/ar', 'WebSite');

    expect($website)->not->toBeNull()
        ->and($website['url'])->toContain('/ar')
        ->and($website['potentialAction']['@type'])->toBe('SearchAction')
        ->and($website['potentialAction']['target']['urlTemplate'])->toContain('search_term_string');
});

it('describes the organisation', function (): void {
    HomepageLayout::factory()->create(['is_active' => true]);

    $org = schemaOfType('/ar', 'NewsMediaOrganization');

    expect($org)->not->toBeNull()->and($org['name'])->not->toBeEmpty();
});

it('emits a complete NewsArticle for an article', function (): void {
    $article = schemaArticle();

    $schema = schemaOfType("/ar/saudi/{$article->slug}", 'NewsArticle');

    expect($schema)->not->toBeNull()
        ->and($schema['headline'])->toBe($article->title)
        ->and($schema['inLanguage'])->toBe('ar')
        ->and($schema['datePublished'])->not->toBeEmpty()
        ->and($schema['dateModified'])->not->toBeEmpty()
        ->and($schema['mainEntityOfPage']['@id'])->toContain('/ar/saudi/'.$article->slug)
        ->and($schema['author']['@type'])->toBe('Person')
        ->and($schema['publisher']['@type'])->toBe('NewsMediaOrganization');
});

it('emits breadcrumbs in order', function (): void {
    $article = schemaArticle();

    $crumbs = schemaOfType("/ar/saudi/{$article->slug}", 'BreadcrumbList');

    expect($crumbs)->not->toBeNull()
        ->and($crumbs['itemListElement'])->toHaveCount(3)
        ->and($crumbs['itemListElement'][0]['position'])->toBe(1)
        ->and($crumbs['itemListElement'][2]['name'])->toBe($article->title);
});

it('declares a sponsor when the article is sponsored', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);
    $article = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'slug' => 'sponsored-schema',
        'category_id' => $category->id,
        'is_sponsored' => true,
        'sponsor_name' => 'مجموعة الأفق',
    ]);

    $schema = schemaOfType("/ar/saudi/{$article->slug}", 'NewsArticle');

    // Disclosed to search engines as well as to readers.
    expect($schema['sponsor']['name'])->toBe('مجموعة الأفق');
});

it('omits empty fields rather than emitting nulls', function (): void {
    $article = schemaArticle();

    $schema = schemaOfType("/ar/saudi/{$article->slug}", 'NewsArticle');

    foreach ($schema as $key => $value) {
        expect($value)->not->toBeNull("{$key} should be omitted rather than null");
    }
});

it('produces a valid sitemap', function (): void {
    schemaArticle();

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('<urlset');
});

it('produces a news sitemap limited to the last 48 hours', function (): void {
    schemaArticle();

    Article::query()->update(['published_at' => now()->subDays(10)]);

    $xml = $this->get('/news-sitemap.xml')->assertOk()->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->not->toContain('<loc>');
});

it('produces a valid rss feed', function (): void {
    $article = schemaArticle();

    $xml = $this->get('/ar/rss.xml')->assertOk()->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain($article->title);
});

it('serves robots.txt with a sitemap reference', function (): void {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap:', false);
});
