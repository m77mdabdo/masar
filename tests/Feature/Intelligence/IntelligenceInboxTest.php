<?php

declare(strict_types=1);

use App\Actions\Intelligence\TriageIntelligenceItem;
use App\Enums\ReviewState;
use App\Filament\Resources\Intelligence\IntelligenceItemResource;
use App\Filament\Resources\Intelligence\Pages\ListIntelligenceItems;
use App\Models\IntelligenceItem;
use App\Models\SourceItem;

use function Pest\Livewire\livewire;

/**
 * The pipeline worked for months and delivered into a table no editor could
 * open. These cover the surface that makes it an inbox rather than a cron job.
 */
it('is reachable by the roles that triage and closed to those that do not', function (string $role, bool $allowed): void {
    $this->actingAs(staff($role));

    expect(IntelligenceItemResource::canViewAny())->toBe($allowed);
})->with([
    ['editor_in_chief', true],
    ['editor', true],
    ['researcher', true],
    ['writer', false],
    ['designer', false],
]);

it('lists what the pipeline found', function (): void {
    $this->actingAs(staff('editor'));
    $item = IntelligenceItem::factory()->create([
        'review_state' => ReviewState::New,
        'title' => 'إعلان عن مشروع في الرياض',
    ]);

    livewire(ListIntelligenceItems::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$item]);
});

it('never renders the publisher prose it was built to keep internal', function (): void {
    // CLAUDE.md §5: raw body text is internal for its whole life, and must not
    // sit in a Filament field an editor can copy from. This is why the inbox is
    // built on IntelligenceItem and not on SourceItem.
    $this->actingAs(staff('editor'));

    $sourceItem = SourceItem::factory()->create([
        'raw_body' => 'HET_RAW_BODY_MARKER نص المصدر الكامل الذي لا يجوز عرضه',
    ]);
    $item = IntelligenceItem::factory()->create([
        'source_item_id' => $sourceItem->getKey(),
        'source_id' => $sourceItem->source_id,
        'review_state' => ReviewState::New,
    ]);

    // Prove the prose is really there first, or "not in the page" is a test
    // that would pass against an empty column.
    expect(SourceItem::query()->whereKey($sourceItem->getKey())->value('raw_body'))
        ->toContain('HET_RAW_BODY_MARKER')
        ->and($item->sourceItem)->not->toBeNull();

    $html = livewire(ListIntelligenceItems::class)->assertOk()->html();

    expect($html)->not->toContain('HET_RAW_BODY_MARKER')
        // And the item itself is genuinely on the page, so the absence of the
        // prose is not just the absence of the row.
        ->and($html)->toContain(e($item->title));
});

it('records who triaged an item and when', function (): void {
    $actor = staff('editor');
    $item = IntelligenceItem::factory()->create(['review_state' => ReviewState::New]);

    app(TriageIntelligenceItem::class)($item, ReviewState::Approved, $actor);

    $item->refresh();
    expect($item->review_state)->toBe(ReviewState::Approved)
        ->and($item->reviewed_by_id)->toBe($actor->getKey())
        ->and($item->reviewed_at)->not->toBeNull();
});

it('returns a dismissed item to the queue and clears the purge clock', function (): void {
    // The recovery half of the 30-day window. `reviewed_at` is what the purge
    // scope reads, so a restored item must not keep an old timestamp or it
    // would be purged while sitting in the queue.
    $actor = staff('editor');
    $item = IntelligenceItem::factory()->create(['review_state' => ReviewState::New]);

    app(TriageIntelligenceItem::class)($item, ReviewState::Rejected, $actor);
    expect($item->fresh()->reviewed_at)->not->toBeNull();

    app(TriageIntelligenceItem::class)($item, ReviewState::New, $actor);

    $item->refresh();
    expect($item->review_state)->toBe(ReviewState::New)
        ->and($item->reviewed_at)->toBeNull()
        ->and($item->reviewed_by_id)->toBeNull()
        ->and(IntelligenceItem::query()->purgeable()->count())->toBe(0);
});

it('refuses to claim a draft that does not exist', function (): void {
    $item = IntelligenceItem::factory()->create([
        'review_state' => ReviewState::New,
        'article_id' => null,
    ]);

    expect(fn () => app(TriageIntelligenceItem::class)($item, ReviewState::Drafted, staff('editor')))
        ->toThrow(RuntimeException::class);
});

it('offers no way to create or edit a row by hand', function (): void {
    // The inbox is what the pipeline found. An editor triages it; they do not
    // type into it.
    expect(IntelligenceItemResource::canCreate())->toBeFalse()
        ->and(IntelligenceItemResource::getPages())->toHaveCount(1)
        ->and(array_keys(IntelligenceItemResource::getPages()))->toBe(['index']);
});

it('counts only the untriaged in the navigation badge', function (): void {
    IntelligenceItem::factory()->count(3)->create(['review_state' => ReviewState::New]);
    IntelligenceItem::factory()->count(2)->create(['review_state' => ReviewState::Approved]);

    expect(IntelligenceItemResource::getNavigationBadge())->toBe('3');
});
