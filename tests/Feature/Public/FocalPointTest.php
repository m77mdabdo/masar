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
