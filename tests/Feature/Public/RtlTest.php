<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;

/**
 * RTL correctness, checked against rendered output rather than source.
 *
 * A physical margin in a component is invisible in review and only shows up as a
 * layout that looks subtly wrong in Arabic — which is every page on this site.
 */
function renderedClasses(string $path): string
{
    $html = test()->get($path)->assertOk()->getContent();

    preg_match_all('/class="([^"]*)"/', $html, $matches);

    return implode(' ', $matches[1]);
}

beforeEach(function (): void {
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);

    $layout = HomepageLayout::factory()->create(['is_active' => true]);
    $layout->sections()->create([
        'type' => 'leads', 'title' => ['ar' => 'الأبرز'], 'source' => 'auto',
        'config' => ['limit' => 4], 'sort_order' => 1, 'is_visible' => true,
    ]);

    Article::factory()->count(6)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => $category->id,
    ]);
});

it('uses no physical direction utilities', function (string $path): void {
    preg_match_all('/\b(ml|mr|pl|pr)-[0-9a-z.\/\[\]]+/', renderedClasses($path), $offenders);

    expect(array_unique($offenders[0]))->toBe([]);
})->with([
    '/ar',
    '/ar/saudi',
    '/ar/opportunities',
    '/ar/search?q=x',
]);

it('uses no physical direction utilities on an article page', function (): void {
    $article = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'slug' => 'rtl-check',
        'category_id' => Category::query()->where('slug', 'saudi')->value('id'),
    ]);

    preg_match_all('/\b(ml|mr|pl|pr)-[0-9a-z.\/\[\]]+/', renderedClasses("/ar/saudi/{$article->slug}"), $offenders);

    expect(array_unique($offenders[0]))->toBe([]);
});

it('ships no physical direction utilities in any blade template', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        // Class attributes only: `pr-` inside a word or a PHP expression is not
        // a Tailwind utility.
        preg_match_all('/class="([^"]*)"/', $source, $classAttributes);

        foreach ($classAttributes[1] as $attribute) {
            if (preg_match('/\b(ml|mr|pl|pr)-[0-9a-z.\/\[\]]+/', $attribute, $match) === 1) {
                $offenders[] = basename($file->getPathname()).' → '.$match[0];
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('forces western numerals globally', function (): void {
    $css = file_get_contents(resource_path('css/app.css'));

    // Arabic-Indic digits are a locked "never", and some faces default to them.
    expect($css)->toContain('lining-nums');
});

it('isolates latin runs inside arabic text', function (): void {
    $html = $this->get('/ar')->assertOk()->getContent();

    expect($html)->toContain('ltr-isolate');
});

it('declares the document direction and language', function (): void {
    $this->get('/ar')->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
});
