<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;

/**
 * The parts of accessibility a test can hold.
 *
 * axe-core runs against a live server and is reported separately; these lock in
 * the structural properties that axe checks, so a regression fails the suite
 * rather than waiting for the next manual audit.
 */
function accessiblePage(string $path): string
{
    return test()->get($path)->assertOk()->getContent();
}

function contrastRatio(string $foreground, string $background): float
{
    $luminance = function (string $hex): float {
        $channels = array_map(fn (string $pair): float => hexdec($pair) / 255, str_split(ltrim($hex, '#'), 2));
        $channels = array_map(
            fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };

    $a = $luminance($foreground);
    $b = $luminance($background);

    return round((max($a, $b) + 0.05) / (min($a, $b) + 0.05), 2);
}

beforeEach(function (): void {
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);

    Article::factory()->count(6)->withHeroImage()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => $category->id,
    ]);

    // Without an active layout the homepage renders no cards, and an image
    // assertion would pass by finding nothing.
    $layout = HomepageLayout::factory()->create(['is_active' => true]);
    $layout->sections()->create([
        'type' => 'leads', 'title' => ['ar' => 'الأبرز'], 'source' => 'auto',
        'config' => ['limit' => 4], 'sort_order' => 1, 'is_visible' => true,
    ]);
});

it('meets AA contrast for every text token on its own ground', function (array $pair): void {
    [$fg, $bg, $label] = $pair;

    expect(contrastRatio($fg, $bg))->toBeGreaterThanOrEqual(4.5, $label);
})->with([
    'body text on cream' => [['#14181A', '#F6F4EF', 'ink on cream']],
    'tertiary on cream' => [['#61696F', '#F6F4EF', 'ink-3 on cream — was #7C848A at 3.46']],
    'links on cream' => [['#1A4531', '#F6F4EF', 'g-700 on cream']],
    'positive on cream' => [['#1E5E3F', '#F6F4EF', 'g-600 on cream']],
    'gold text on white' => [['#8F6828', '#FFFFFF', 'gold-ink on white — plain gold is 2.42']],
    'cream on dark' => [['#F6F4EF', '#0E2A1C', 'cream on g-900']],
    'mint on dark' => [['#7FB69A', '#0E2A1C', 'mint on g-900']],
]);

it('never fades a text token below the ratio it was chosen for', function (): void {
    // `ink-3` is #61696F: 5.08:1 on cream, which is the whole reason it replaced
    // #7C848A. At 70% opacity it measures 2.82 and the choice is undone — and
    // the opacity modifier makes that invisible to anyone reading the class.
    //
    // Disabled controls are exempt under WCAG 1.4.3, so the one place that does
    // fade a token is the inactive pagination arrow.
    $allowed = ['masar.blade.php'];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || in_array($file->getFilename(), $allowed, true)) {
            continue;
        }

        expect((string) file_get_contents($file->getPathname()))
            ->not->toMatch('/\btext-(?:ink|ink-3|g-600|g-700|gold-ink)\/\d+/');
    }
});

it('never uses mint or plain gold as text on a light ground', function (): void {
    // Both are accents: 2.11 and 2.42 on cream. They are fills, not foregrounds.
    expect(contrastRatio('#7FB69A', '#F6F4EF'))->toBeLessThan(4.5)
        ->and(contrastRatio('#C9A063', '#FFFFFF'))->toBeLessThan(4.5);

    // A flat scan cannot tell which ground an element sits on, and mint on
    // g-950 measures 7.78 — correct, and used deliberately. So the check is not
    // a ban and not a list of filenames that quietly grows: a component using
    // either token as a foreground must say, in the file, what ground it sits
    // on. An unexplained use fails.
    //
    //     {{-- contrast-safe: renders on g-950 --}}
    //
    $marker = 'contrast-safe:';

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    $unexplained = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $markup = (string) file_get_contents($file->getPathname());

        $usesAccent = str_contains($markup, 'text-mint')
            || preg_match('/\btext-gold\b(?!-ink)/', $markup) === 1;

        if ($usesAccent && ! str_contains($markup, $marker)) {
            $unexplained[] = $file->getFilename();
        }
    }

    expect($unexplained)->toBe([], 'Accent used as text with no stated ground');
});

it('contains all content within landmarks', function (string $path): void {
    $html = accessiblePage($path);

    // axe "region": content outside a landmark is unreachable by landmark
    // navigation, which is how many screen-reader users move through a page.
    expect($html)->toContain('<main id="main">')
        ->and($html)->toContain('<nav')
        ->and($html)->toContain('<footer');
})->with(['/ar', '/ar/saudi', '/ar/opportunities']);

it('never skips a heading level', function (string $path): void {
    preg_match_all('/<h([1-6])\b/', accessiblePage($path), $matches);

    $levels = array_map('intval', $matches[1]);

    expect($levels)->not->toBeEmpty()
        ->and($levels[0])->toBe(1);

    foreach ($levels as $index => $level) {
        if ($index === 0) {
            continue;
        }

        expect($level - $levels[$index - 1])->toBeLessThanOrEqual(1, "jumped to h{$level} on {$path}");
    }
})->with(['/ar', '/ar/saudi', '/ar/opportunities']);

it('offers a skip link before the navigation', function (): void {
    $html = accessiblePage('/ar');

    expect($html)->toContain('href="#main"')
        ->and(strpos($html, 'href="#main"'))->toBeLessThan((int) strpos($html, '<main'));
});

it('keeps a visible focus indicator', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain(':focus-visible')->toContain('outline');
});

it('honours prefers-reduced-motion', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('prefers-reduced-motion');
});

it('traps focus inside the mobile drawer', function (): void {
    // aria-modal without a focus trap lets a keyboard user tab into content the
    // screen reader is simultaneously announcing as hidden.
    $markup = (string) file_get_contents(resource_path('views/components/layout/mobile-nav.blade.php'));

    expect($markup)->toContain('aria-modal="true"')->toContain('x-trap');
});

it('closes the mega menu and drawer with escape', function (): void {
    foreach (['mega-menu', 'mobile-nav'] as $component) {
        $markup = (string) file_get_contents(resource_path("views/components/layout/{$component}.blade.php"));

        expect($markup)->toContain('keydown.escape');
    }
});

it('gives every image real alt text or marks it decorative', function (): void {
    $html = accessiblePage('/ar');

    preg_match_all('/<img[^>]*>/', $html, $matches);

    expect($matches[0])->not->toBeEmpty();

    foreach ($matches[0] as $img) {
        expect($img)->toMatch('/\balt="/');

        // A filename is not alt text.
        preg_match('/alt="([^"]*)"/', $img, $alt);
        expect($alt[1] ?? '')->not->toMatch('/\.(jpe?g|png|webp|avif)$/i');
    }
});

it('labels every icon-only control', function (): void {
    $html = accessiblePage('/ar');

    preg_match_all('/<(?:button|a)\b[^>]*>(?:\s*<svg.*?<\/svg>\s*)<\/(?:button|a)>/s', $html, $matches);

    foreach ($matches[0] as $control) {
        expect($control)->toMatch('/aria-label=|aria-hidden="true"/');
    }
});

/**
 * The admin theme's overrides have to target class names Filament renders.
 *
 * Dead CSS fails silently — no error, no warning, and a theme that looks
 * finished — and in this case it left the current page's sidebar label at
 * 1.05:1 against its own background for the life of the project. Asserting the
 * mechanism: the selectors exist in the markup Filament produces.
 */
it('targets filament class names that actually exist', function (): void {
    // Comments only, stripped: the note explaining this bug names the dead
    // selectors, and a test that cannot tell a rule from a comment would fail
    // on its own documentation.
    $theme = (string) preg_replace(
        '#/\*.*?\*/#s',
        '',
        (string) file_get_contents(resource_path('css/filament/admin/theme.css')),
    );

    // The two the active nav item depends on. Filament renders `fi-active` and
    // `fi-sidebar-item-btn`; the plausible-looking `fi-sidebar-item-active` and
    // `fi-sidebar-item-button` match nothing.
    expect($theme)->toContain('.fi-sidebar-item.fi-active .fi-sidebar-item-btn')
        ->and($theme)->not->toContain('.fi-sidebar-item-active ')
        ->and($theme)->not->toContain('.fi-sidebar-item-button');

    // And the active item must state its own foreground: every sidebar label is
    // cream, and the active pill is not.
    expect($theme)->toContain('.fi-sidebar-item.fi-active .fi-sidebar-item-label');
});
