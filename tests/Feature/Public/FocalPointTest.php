<?php

declare(strict_types=1);

use App\Models\Article;
use App\Support\EntityUrl;
use Illuminate\Support\Facades\DB;

/*
 * A media record's `focal` decides where the crop sits, on the big story and
 * the article hero alike; without one the default 50% 40% applies.
 */
it('crops the article hero around the photograph’s own focal point', function (): void {
    $article = publishableArticle(['status' => \App\Enums\ArticleStatus::Published, 'published_at' => now()->subHour()]);
    $article->heroMedia->setCustomProperty('focal', '40% 40%')->save();

    $this->get(app(EntityUrl::class)->for($article->fresh(), 'ar'))->assertOk()->assertSee('object-position: 40% 40%', false);
});

it('sets the two measured focal points and rolls back only its own values', function (): void {
    $migration = require database_path('migrations/2026_10_29_110000_set_focal_points_on_two_hero_photographs.php');
    $article = Article::factory()->withHeroImage()->create();
    $mine = $article->heroMedia;
    $mine->setCustomProperty('source_file', 'pexels-tima-miroshnichenko-5717315.jpg')->save();
    $edited = Article::factory()->withHeroImage()->create()->heroMedia;
    $edited->setCustomProperty('source_file', 'pexels-aburhman187-29859597.jpg')->save();

    $migration->up();
    expect($mine->fresh()->getCustomProperty('focal'))->toBe('40% 40%')
        ->and($edited->fresh()->getCustomProperty('focal'))->toBe('56% 40%');

    // An editor moves one; rolling back must leave theirs alone.
    $edited->fresh()->setCustomProperty('focal', '60% 30%')->save();
    $migration->down();

    expect($mine->fresh()->getCustomProperty('focal'))->toBeNull()
        ->and($edited->fresh()->getCustomProperty('focal'))->toBe('60% 30%');
});

it('gives the near-square tablet stage its own crop centre when the image has one', function (): void {
    // The towers photograph: 56% on phones, 65% at 768–1023 so the right-hand
    // tower survives the zoom's start (measured: right edge 0.883 vs 0.878).
    $css = file_get_contents(resource_path('css/app.css'));
    expect($css)->toContain('@media (min-width: 48rem) and (max-width: 63.999rem)')
        ->toContain('--focal-at: var(--focal-md, var(--focal, 50% 50%));')
        ->toContain('transform-origin: var(--focal-at, var(--focal, 50% 50%));');

    $slides = collect(range(1, 2))->map(fn () => \App\Models\Article::factory()->withHeroImage()->create());
    $slides[1]->heroMedia->setCustomProperty('focal', '56% 40%')->setCustomProperty('focal_md', '65% 40%')->save();

    $html = view('components.home.big-story', [
        'section' => ['title' => 'x', 'items' => $slides->map->fresh()], 'backdrop' => null,
        'attributes' => new \Illuminate\View\ComponentAttributeBag,
    ])->render();

    expect($html)->toContain('--focal: 56% 40%; --focal-md: 65% 40%;')
        ->toContain('object-position: var(--focal-at)');
});
