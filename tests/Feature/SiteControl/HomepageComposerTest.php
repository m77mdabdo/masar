<?php

declare(strict_types=1);

use App\Actions\Homepage\ActivateLayout;
use App\Actions\Homepage\DuplicateLayout;
use App\Enums\ArticleStatus;
use App\Filament\Pages\HomepageComposer;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;
use App\Queries\ComposeHomepage;
use Illuminate\Support\Collection;

use function Pest\Livewire\livewire;

function layoutWith(array $sections): HomepageLayout
{
    $layout = HomepageLayout::factory()->create(['is_active' => false]);

    foreach ($sections as $index => $section) {
        $layout->sections()->create([
            'type' => $section['type'],
            'title' => ['ar' => $section['title'] ?? 'قسم'],
            'source' => $section['source'] ?? 'auto',
            'config' => $section['config'] ?? ['limit' => 3],
            'sort_order' => $index + 1,
            'is_visible' => $section['is_visible'] ?? true,
        ]);
    }

    return $layout->refresh();
}

function homepageArticles(int $count, array $attributes = []): Collection
{
    return Article::factory()->count($count)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        ...$attributes,
    ]);
}

it('allows only one active layout', function (): void {
    $first = HomepageLayout::factory()->create(['is_active' => true]);
    $second = HomepageLayout::factory()->create(['is_active' => false]);

    app(ActivateLayout::class)($second);

    expect($second->fresh()->is_active)->toBeTrue()
        ->and($first->fresh()->is_active)->toBeFalse()
        ->and(HomepageLayout::where('is_active', true)->count())->toBe(1);
});

it('never lets two layouts be active at once', function (): void {
    HomepageLayout::factory()->count(3)->create(['is_active' => true]);
    $chosen = HomepageLayout::factory()->create(['is_active' => false]);

    app(ActivateLayout::class)($chosen);

    expect(HomepageLayout::where('is_active', true)->pluck('id')->all())->toBe([$chosen->id]);
});

it('never places the same article twice on one page', function (): void {
    homepageArticles(6);

    $page = app(ComposeHomepage::class)(layoutWith([
        ['type' => 'big_story', 'config' => ['limit' => 1]],
        ['type' => 'leads', 'config' => ['limit' => 3]],
        ['type' => 'tiles', 'config' => ['limit' => 3]],
    ]), 'ar');

    $ids = $page->flatMap(fn (array $s): array => $s['items']->pluck('id')->all())->all();

    expect($ids)->toHaveCount(count(array_unique($ids)))
        ->and($ids)->not->toBeEmpty();
});

it('gives a pinned article to the section that pinned it, not a later one', function (): void {
    $articles = homepageArticles(5);
    $pinned = $articles->first();

    $page = app(ComposeHomepage::class)(layoutWith([
        ['type' => 'big_story', 'source' => 'manual', 'config' => ['limit' => 1, 'article_ids' => [$pinned->id]]],
        ['type' => 'leads', 'config' => ['limit' => 4]],
    ]), 'ar');

    expect($page[0]['items']->pluck('id')->all())->toBe([$pinned->id])
        ->and($page[1]['items']->pluck('id')->all())->not->toContain($pinned->id);
});

it('fills a mixed section with pinned items first, then auto', function (): void {
    $articles = homepageArticles(6);
    $pinned = $articles->take(2);

    $page = app(ComposeHomepage::class)(layoutWith([
        ['type' => 'leads', 'source' => 'mixed', 'config' => [
            'limit' => 4,
            'article_ids' => $pinned->pluck('id')->all(),
        ]],
    ]), 'ar');

    $ids = $page[0]['items']->pluck('id')->all();

    expect($ids)->toHaveCount(4)
        ->and(array_slice($ids, 0, 2))->toBe($pinned->pluck('id')->all());
});

it('does not auto-fill a manual section that is short', function (): void {
    $articles = homepageArticles(6);

    $page = app(ComposeHomepage::class)(layoutWith([
        ['type' => 'leads', 'source' => 'manual', 'config' => [
            'limit' => 5,
            'article_ids' => [$articles->first()->id],
        ]],
    ]), 'ar');

    // An editor who picked one story wants one, not one plus four surprises.
    expect($page[0]['items'])->toHaveCount(1);
});

it('filters an auto section by category', function (): void {
    $saudi = Category::factory()->create(['slug' => 'saudi']);
    $other = Category::factory()->create(['slug' => 'business']);

    homepageArticles(3, ['category_id' => $saudi->id]);
    homepageArticles(3, ['category_id' => $other->id]);

    $page = app(ComposeHomepage::class)(layoutWith([
        ['type' => 'saudi', 'config' => ['limit' => 5, 'category_slug' => 'saudi']],
    ]), 'ar');

    expect($page[0]['items'])->toHaveCount(3)
        ->and($page[0]['items']->pluck('category_id')->unique()->all())->toBe([$saudi->id]);
});

it('skips hidden sections entirely', function (): void {
    homepageArticles(4);

    $page = app(ComposeHomepage::class)(layoutWith([
        ['type' => 'leads', 'is_visible' => false],
        ['type' => 'tiles'],
    ]), 'ar');

    expect($page)->toHaveCount(1)
        ->and($page[0]['type'])->toBe('tiles');
});

it('resolves a static section with no items', function (): void {
    $page = app(ComposeHomepage::class)(layoutWith([
        ['type' => 'newsletter'],
    ]), 'ar');

    expect($page[0]['items'])->toBeEmpty()
        ->and($page[0]['type'])->toBe('newsletter');
});

it('excludes drafts and future-dated articles', function (): void {
    Article::factory()->create(['status' => ArticleStatus::Writing, 'locale' => 'ar']);
    Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->addWeek(),
        'locale' => 'ar',
    ]);
    $live = homepageArticles(1)->first();

    $page = app(ComposeHomepage::class)(layoutWith([['type' => 'leads']]), 'ar');

    expect($page[0]['items']->pluck('id')->all())->toBe([$live->id]);
});

it('duplicates a layout with its sections, inactive', function (): void {
    $original = layoutWith([
        ['type' => 'big_story'],
        ['type' => 'leads'],
    ]);
    app(ActivateLayout::class)($original);

    $copy = app(DuplicateLayout::class)($original, 'حملة رمضان');

    expect($copy->name)->toBe('حملة رمضان')
        // Duplicating must never change what readers see.
        ->and($copy->is_active)->toBeFalse()
        ->and($copy->sections()->count())->toBe(2)
        ->and($original->fresh()->is_active)->toBeTrue();
});

it('orders sections by sort order', function (): void {
    homepageArticles(4);

    $layout = layoutWith([['type' => 'leads'], ['type' => 'tiles'], ['type' => 'stories']]);
    $layout->sections()->orderBy('sort_order')->get()
        ->each(fn ($s, $i) => $s->forceFill(['sort_order' => 3 - $i])->save());

    $page = app(ComposeHomepage::class)($layout->refresh(), 'ar');

    expect($page->pluck('type')->all())->toBe(['stories', 'tiles', 'leads']);
});

it('moves a section from the keyboard, not only by dragging', function (): void {
    $layout = HomepageLayout::factory()->create(['is_active' => true]);

    foreach (['big_story', 'tiles', 'leads'] as $i => $type) {
        $layout->sections()->create([
            'type' => $type, 'title' => ['ar' => $type], 'source' => 'auto',
            'config' => ['limit' => 3], 'sort_order' => $i + 1, 'is_visible' => true,
        ]);
    }

    $leads = $layout->sections()->where('type', 'leads')->first();

    $this->actingAs(admin());

    // HTML5 drag-and-drop cannot be driven from a keyboard, so the page had no
    // reorder an editor without a pointer could reach at all.
    livewire(HomepageComposer::class)->call('moveSection', $leads->id, -1);

    expect($layout->sections()->orderBy('sort_order')->pluck('type')->all())
        ->toBe(['big_story', 'leads', 'tiles']);
});

it('refuses to move a section past either end or out of its layout', function (): void {
    $layout = HomepageLayout::factory()->create(['is_active' => true]);
    $other = HomepageLayout::factory()->create(['is_active' => false]);

    $first = $layout->sections()->create([
        'type' => 'big_story', 'title' => ['ar' => 'أ'], 'source' => 'auto',
        'config' => ['limit' => 1], 'sort_order' => 1, 'is_visible' => true,
    ]);
    $layout->sections()->create([
        'type' => 'tiles', 'title' => ['ar' => 'ب'], 'source' => 'auto',
        'config' => ['limit' => 6], 'sort_order' => 2, 'is_visible' => true,
    ]);
    $foreign = $other->sections()->create([
        'type' => 'leads', 'title' => ['ar' => 'ج'], 'source' => 'auto',
        'config' => ['limit' => 3], 'sort_order' => 1, 'is_visible' => true,
    ]);

    $this->actingAs(admin());

    livewire(HomepageComposer::class)
        ->call('moveSection', $first->id, -1)        // already first
        ->call('moveSection', $first->id, 5)         // not a direction
        ->call('moveSection', $foreign->id, 1);      // someone else's layout

    expect($layout->sections()->orderBy('sort_order')->pluck('type')->all())
        ->toBe(['big_story', 'tiles'])
        ->and($foreign->fresh()->sort_order)->toBe(1);
});
