<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\EntityMention;
use App\Models\HomepageLayout;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Subscriber;
use App\Models\Topic;
use App\Support\DemoContent;
use App\Support\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The exit for demo content, proved the way the brief asks: snapshot every
 * table, seed demo content on top of real content with references running in
 * both directions, purge, and require every table to be back to its count —
 * with the real rows untouched and nothing left pointing at a purged id.
 */

/** @return array<string, int> row count of every table in the test database */
function tableCounts(): array
{
    $counts = [];

    foreach (DB::select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        $counts[$table] = DB::table($table)->count();
    }

    ksort($counts);

    return $counts;
}

function mention(Illuminate\Database\Eloquent\Model $content, Illuminate\Database\Eloquent\Model $entity): EntityMention
{
    return EntityMention::query()->create([
        'mentionable_type' => $content->getMorphClass(),
        'mentionable_id' => $content->getKey(),
        'entity_type' => $entity->getMorphClass(),
        'entity_id' => $entity->getKey(),
        'role' => 'primary',
        'prominence' => 80,
    ]);
}

/**
 * Real content first — the part that must survive — then a demo graph wired
 * into it from both sides.
 *
 * @return array<string, mixed>
 */
function realAndDemoGraph(): array
{
    Storage::fake('public');

    $chief = staff('editor_in_chief');
    $category = Category::factory()->create(['slug' => 'saudi']);
    $realCompany = Company::factory()->create();
    $realArticle = Article::factory()->withBlocks()->withSources()->withHeroImage()->create([
        'category_id' => $category->id, 'author_id' => $chief->id,
        'status' => ArticleStatus::Published, 'published_at' => now()->subDay(),
    ]);
    mention($realArticle, $realCompany);

    $layout = HomepageLayout::factory()->create(['is_active' => true]);
    $section = $layout->sections()->create([
        'type' => 'big_story', 'title' => ['ar' => 'القصة الكبرى'], 'source' => 'manual',
        'config' => ['limit' => 3, 'article_ids' => [$realArticle->id]], 'sort_order' => 1, 'is_visible' => true,
    ]);
    $menu = Menu::factory()->create(['key' => 'header']);

    $before = tableCounts();

    $demo = DemoContent::record(function () use ($category, $realCompany, $realArticle, $section, $menu): array {
        $writer = staff('writer');
        $company = Company::factory()->create();
        $person = Person::factory()->create(['company_id' => $company->id]);
        $topic = Topic::factory()->create();
        $article = Article::factory()->withBlocks()->withSources()->withHeroImage()->create([
            'category_id' => $category->id, 'author_id' => $writer->id,
            'status' => ArticleStatus::Published, 'published_at' => now()->subHours(2),
        ]);
        $article->topics()->attach($topic);
        $opportunity = Opportunity::factory()->create();

        mention($article, $company);
        mention($article, $realCompany);      // demo content mentioning a real entity
        mention($realArticle, $company);      // real content mentioning a demo entity
        mention($opportunity, $company);

        Subscriber::factory()->count(3)->create();
        app(Settings::class)->set('market.as_of', '2026-10-01 12:00');

        $section->update(['config' => ['limit' => 3, 'article_ids' => [$realArticle->id, $article->id]]]);
        MenuItem::factory()->create([
            'menu_id' => $menu->id, 'label' => ['ar' => 'شركة'], 'url' => null,
            'linkable_type' => $company->getMorphClass(), 'linkable_id' => $company->id,
        ]);

        return compact('writer', 'company', 'person', 'topic', 'article', 'opportunity');
    });

    return compact('before', 'realArticle', 'realCompany', 'section', 'demo');
}

it('flags what was created while recording, including rows written without Eloquent', function (): void {
    ['demo' => $demo, 'realArticle' => $real] = realAndDemoGraph();

    expect($demo['article']->fresh()->is_demo)->toBeTrue()
        ->and($real->fresh()->is_demo)->toBeFalse()
        // Blocks and hero media are written by a raw insert in the factory,
        // so these come from the sweep, not the model event.
        ->and(DB::table('article_blocks')->where('article_id', $demo['article']->id)->where('is_demo', false)->count())->toBe(0)
        ->and(DB::table('media')->where('model_type', 'article')->where('model_id', $demo['article']->id)->value('is_demo'))->toBe(1)
        ->and(DB::table('entity_mentions')->where('mentionable_id', $real->id)->where('mentionable_type', 'article')->where('entity_id', $demo['company']->id)->value('is_demo'))->toBe(1)
        ->and(DB::table('activity_log')->where('is_demo', true)->exists())->toBeTrue();
});

it('changes nothing on a dry run', function (): void {
    realAndDemoGraph();
    $seeded = tableCounts();

    $this->artisan('masar:purge-demo --dry-run')->expectsOutputToContain('Dry run')->assertSuccessful();

    expect(tableCounts())->toBe($seeded);
});

it('returns every table to its pre-seed count and leaves the real graph intact', function (): void {
    ['before' => $before, 'realArticle' => $real, 'realCompany' => $realCompany, 'section' => $section, 'demo' => $demo] = realAndDemoGraph();

    expect(tableCounts())->not->toBe($before);

    $this->artisan('masar:purge-demo --force')
        ->expectsOutputToContain('No reference to it remains')
        ->assertSuccessful();

    expect(tableCounts())->toBe($before)
        ->and(Article::query()->whereKey($real->id)->exists())->toBeTrue()
        ->and($real->fresh()->blocks()->count())->toBeGreaterThan(0)
        ->and(EntityMention::query()->where('mentionable_id', $real->id)->where('entity_id', $realCompany->id)->exists())->toBeTrue()
        ->and($section->fresh()->config['article_ids'])->toBe([$real->id])
        ->and(Article::withTrashed()->whereKey($demo['article']->id)->exists())->toBeFalse();
});

it('refuses rather than half-purge when a demo user wrote real content', function (): void {
    ['demo' => $demo] = realAndDemoGraph();

    Article::factory()->create(['author_id' => $demo['writer']->id]);
    $seeded = tableCounts();

    $this->artisan('masar:purge-demo --force')->expectsOutputToContain('Refusing')->assertFailed();

    expect(tableCounts())->toBe($seeded);
});

it('says so when there is nothing to remove', function (): void {
    $this->artisan('masar:purge-demo --force')->expectsOutputToContain('No demo content')->assertSuccessful();
});

it('never lets an editor set the flag', function (): void {
    $company = Company::factory()->create();
    $company->fill(['is_demo' => true])->save();

    expect($company->fresh()->is_demo)->toBeFalse();
});
