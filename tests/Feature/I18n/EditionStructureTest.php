<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\MenuItem;
use Database\Seeders\EditionLabelsSeeder;
use Illuminate\Support\Facades\DB;

/*
 * The structured half of the graph in every edition. The seeder is additive:
 * it may add a language, never replace what an editor wrote.
 */
it('labels every section in every edition, and running it twice changes nothing', function (): void {
    $category = Category::factory()->create();
    DB::table('category_translations')->where('category_id', $category->id)->delete();
    DB::table('category_translations')->insert(['category_id' => $category->id, 'locale' => 'ar', 'name' => 'قصص نجاح']);

    (new EditionLabelsSeeder)->run();
    (new EditionLabelsSeeder)->run();

    expect(DB::table('category_translations')->where('category_id', $category->id)->pluck('name', 'locale')->all())
        ->toMatchArray(['ar' => 'قصص نجاح', 'en' => 'Success stories', 'ur-pk' => 'کامیابی کی کہانیاں', 'zh-hans' => '成功故事'])
        ->and(DB::table('category_translations')->where('category_id', $category->id)->count())->toBe(6);
});

it('never overwrites a label someone already wrote', function (): void {
    $item = MenuItem::factory()->create(['label' => ['ar' => 'قصص نجاح', 'en' => 'Stories']]);

    (new EditionLabelsSeeder)->run();

    expect($item->fresh()->label)->toMatchArray(['ar' => 'قصص نجاح', 'en' => 'Stories', 'fr' => 'Histoires de réussite']);
});

it('resolves a per-edition setting to the current edition and leaves other settings alone', function (): void {
    app()->setLocale('en');
    expect(setting('identity.site_name'))->toBe('MASAR')
        ->and(setting('seo.title_template'))->toBe(':title | MASAR');

    app()->setLocale('ur-pk');
    expect(setting('identity.site_name'))->toBe('مسار');

    // A list is not a per-edition map.
    app(\App\Support\Settings::class)->set('market.ticker', [['label' => 'TASI']]);
    expect(setting('market.ticker'))->toBe([['label' => 'TASI']]);
});
