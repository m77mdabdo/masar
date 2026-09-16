<?php

declare(strict_types=1);

use App\Actions\Intelligence\CreateDraftFromIntelligenceItem;
use App\Enums\ArticleStatus;
use App\Enums\ReviewState;
use App\Models\Category;
use App\Models\Company;
use App\Models\IntelligenceItem;
use App\Models\SourceItem;
use Illuminate\Support\Facades\Cache;

/**
 * The frame, never the prose.
 *
 * CLAUDE.md §5: everything published is original MASAR writing with attribution
 * and a link. So a draft made from an inbox item carries the fact that someone
 * published something and where to read it — and not one word of theirs, the
 * headline included.
 */
it('creates a draft that borrows no prose at all', function (): void {
    Category::factory()->create(['slug' => 'business']);
    $actor = staff('editor');

    $sourceItem = SourceItem::factory()->create([
        'raw_title' => 'PUBLISHER_HEADLINE هيئة السوق تقر ضوابط جديدة',
        'raw_body' => 'PUBLISHER_BODY النص الكامل الذي لا يجوز نسخه إطلاقًا',
    ]);
    $item = IntelligenceItem::factory()->create([
        'source_item_id' => $sourceItem->getKey(),
        'source_id' => $sourceItem->source_id,
        'title' => 'PUBLISHER_HEADLINE هيئة السوق تقر ضوابط جديدة',
        'url' => 'https://publisher.test/story-1',
        'review_state' => ReviewState::New,
    ]);

    $article = app(CreateDraftFromIntelligenceItem::class)($item, $actor);

    // Nothing an editor could mistake for their own copy.
    expect($article->title)->not->toContain('PUBLISHER_HEADLINE')
        ->and($article->body)->toBeNull()
        ->and($article->summary)->toBeNull()
        ->and($article->why_it_matters)->toBeNull()
        ->and($article->what_happened)->toBeNull();

    // And nowhere in the record does the body text appear.
    expect(json_encode($article->fresh()->toArray(), JSON_UNESCAPED_UNICODE))
        ->not->toContain('PUBLISHER_BODY');
});

it('attaches the source as a citation, which is where the headline belongs', function (): void {
    Category::factory()->create(['slug' => 'business']);
    $actor = staff('editor');

    $item = IntelligenceItem::factory()->create([
        'title' => 'هيئة السوق تقر ضوابط جديدة',
        'url' => 'https://publisher.test/story-2',
        'review_state' => ReviewState::New,
    ]);

    $article = app(CreateDraftFromIntelligenceItem::class)($item, $actor);
    $source = $article->sources()->sole();

    // Attribution is the opposite of passing it off as ours: the citation names
    // the original piece and links to it.
    expect($source->url)->toBe('https://publisher.test/story-2')
        ->and($source->title)->toBe('هيئة السوق تقر ضوابط جديدة')
        ->and($source->publisher)->not->toBeNull();
});

it('opens the draft in research, owned by the editor who made it', function (): void {
    Category::factory()->create(['slug' => 'business']);
    $actor = staff('editor');
    $item = IntelligenceItem::factory()->create(['review_state' => ReviewState::New]);

    $article = app(CreateDraftFromIntelligenceItem::class)($item, $actor);

    expect($article->status)->toBe(ArticleStatus::Research)
        ->and($article->author_id)->toBe($actor->getKey())
        ->and($article->locale)->toBe('ar')
        // A placeholder slug, because a slug is an editorial decision.
        ->and($article->slug)->toStartWith('draft-');
});

it('links the item to its draft and takes it out of the queue', function (): void {
    Category::factory()->create(['slug' => 'business']);
    $actor = staff('editor');
    $item = IntelligenceItem::factory()->create(['review_state' => ReviewState::New]);

    $article = app(CreateDraftFromIntelligenceItem::class)($item, $actor);
    $item->refresh();

    expect($item->article_id)->toBe($article->getKey())
        ->and($item->review_state)->toBe(ReviewState::Drafted)
        ->and($item->reviewed_by_id)->toBe($actor->getKey());
});

it('refuses to create a second draft for the same item', function (): void {
    Category::factory()->create(['slug' => 'business']);
    $actor = staff('editor');
    $item = IntelligenceItem::factory()->create(['review_state' => ReviewState::New]);

    app(CreateDraftFromIntelligenceItem::class)($item, $actor);

    expect(fn () => app(CreateDraftFromIntelligenceItem::class)($item->fresh(), $actor))
        ->toThrow(RuntimeException::class);
});

it('cannot be published without an editor doing the work', function (): void {
    // The draft is deliberately unpublishable: the gate wants a headline, a
    // summary, why_it_matters and a hero, and this supplies none of them.
    Category::factory()->create(['slug' => 'business']);
    $item = IntelligenceItem::factory()->create(['review_state' => ReviewState::New]);

    $article = app(CreateDraftFromIntelligenceItem::class)($item, staff('editor'));

    // isPublishable() returns the gate's reasons, not a boolean — a non-empty
    // array means it is blocked, and the reasons are the work still to do.
    $reasons = $article->isPublishable();

    expect($reasons)->not->toBeEmpty();
});

it('carries over the entities the headline names, and no topics', function (): void {
    // Naming a company is a fact about the headline. What the story says about
    // that company is writing, and does not travel. Topics have no signal on an
    // intelligence item at all, so guessing one would be an editorial call
    // made inside an action.
    Category::factory()->create(['slug' => 'business']);
    // The factory already makes an `ar` translation; name it rather than add a second.
    $aramco = Company::factory()->create();
    $aramco->translations()->where('locale', 'ar')->update(['name' => 'أرامكو السعودية']);
    Cache::tags(['entities'])->flush();

    $item = IntelligenceItem::factory()->create([
        'title' => 'أرامكو السعودية تعلن نتائج الربع الثالث',
        'review_state' => ReviewState::New,
    ]);

    $article = app(CreateDraftFromIntelligenceItem::class)($item, staff('editor'));

    $mentions = $article->mentions()->get();

    expect($mentions)->toHaveCount(1)
        ->and($mentions->first()->entity_type)->toBe('company')
        ->and((int) $mentions->first()->entity_id)->toBe($aramco->getKey())
        ->and($article->topics()->count())->toBe(0);
});

it('attaches no entity when the headline names none we know', function (): void {
    Category::factory()->create(['slug' => 'business']);
    Company::factory()->create()->translations()->where('locale', 'ar')->update(['name' => 'أرامكو السعودية']);
    Cache::tags(['entities'])->flush();

    $item = IntelligenceItem::factory()->create([
        'title' => 'شركة لم نسمع بها من قبل تعلن شيئًا',
        'review_state' => ReviewState::New,
    ]);

    $article = app(CreateDraftFromIntelligenceItem::class)($item, staff('editor'));

    // Unknown stays unknown. That is the honest answer, not a guess.
    expect($article->mentions()->count())->toBe(0);
});
