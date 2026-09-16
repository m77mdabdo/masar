<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Enums\ReviewState;
use App\Models\IntelligenceItem;
use App\Models\SourceItem;

/**
 * Classification happens once, at ingest, so the keyword table and the document
 * taxonomy are frozen into every stored row. Changing either leaves the whole
 * inbox on the old vintage — 114 items sat on a taxonomy with no `market_move`
 * in it until they were re-derived by hand. This is the command that should
 * have existed for that.
 */
it('reports drift without writing anything on a dry run', function (): void {
    $raw = SourceItem::factory()->create([
        'raw_title' => 'برنت يتجاوز 108 دولارات مع تصاعد المخاوف بشأن الإمدادات',
    ]);
    $item = IntelligenceItem::factory()->create([
        'source_item_id' => $raw->getKey(),
        'source_id' => $raw->source_id,
        // Deliberately wrong, as an old vintage would be.
        'document_type' => DocumentType::Other,
        'review_state' => ReviewState::New,
    ]);

    $this->artisan('masar:reclassify', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run');

    expect($item->fresh()->document_type)->toBe(DocumentType::Other);
});

it('re-derives the document type from the raw text the classifier saw', function (): void {
    $raw = SourceItem::factory()->create([
        'raw_title' => 'برنت يتجاوز 108 دولارات مع تصاعد المخاوف بشأن الإمدادات',
    ]);
    $item = IntelligenceItem::factory()->create([
        'source_item_id' => $raw->getKey(),
        'source_id' => $raw->source_id,
        'document_type' => DocumentType::Other,
        'review_state' => ReviewState::New,
    ]);

    $this->artisan('masar:reclassify')->assertSuccessful();

    expect($item->fresh()->document_type)->toBe(DocumentType::MarketMove);
});

it('never touches an editor judgement', function (): void {
    // Only the machine's reading is recomputed. Triage belongs to the editor.
    $actor = staff('editor');
    $raw = SourceItem::factory()->create(['raw_title' => 'برنت يتجاوز 108 دولارات']);
    $item = IntelligenceItem::factory()->create([
        'source_item_id' => $raw->getKey(),
        'source_id' => $raw->source_id,
        'review_state' => ReviewState::Approved,
        'reviewed_by_id' => $actor->getKey(),
        'reviewed_at' => now()->subDay(),
    ]);

    $this->artisan('masar:reclassify')->assertSuccessful();

    $item->refresh();
    expect($item->review_state)->toBe(ReviewState::Approved)
        ->and($item->reviewed_by_id)->toBe($actor->getKey())
        ->and($item->reviewed_at)->not->toBeNull();
});

it('still reclassifies after the publisher body has been purged', function (): void {
    // Retention nulls `raw_body` and keeps the row; the classifier reads title
    // and summary, so a purged item is still classifiable. `source_item_id` is
    // NOT NULL with a cascading key, so there is no orphan case to handle.
    $raw = SourceItem::factory()->create([
        'raw_title' => 'برنت يتجاوز 108 دولارات مع تصاعد المخاوف بشأن الإمدادات',
        'raw_body' => null,
    ]);
    $item = IntelligenceItem::factory()->create([
        'source_item_id' => $raw->getKey(),
        'source_id' => $raw->source_id,
        'document_type' => DocumentType::Other,
        'review_state' => ReviewState::New,
    ]);

    $this->artisan('masar:reclassify')->assertSuccessful();

    expect($item->fresh()->document_type)->toBe(DocumentType::MarketMove);
});

it('can be limited to a single source', function (): void {
    $keep = SourceItem::factory()->create(['raw_title' => 'برنت يتجاوز 108 دولارات']);
    $other = SourceItem::factory()->create(['raw_title' => 'برنت يتجاوز 108 دولارات']);

    $a = IntelligenceItem::factory()->create([
        'source_item_id' => $keep->getKey(), 'source_id' => $keep->source_id,
        'document_type' => DocumentType::Other, 'review_state' => ReviewState::New,
    ]);
    $b = IntelligenceItem::factory()->create([
        'source_item_id' => $other->getKey(), 'source_id' => $other->source_id,
        'document_type' => DocumentType::Other, 'review_state' => ReviewState::New,
    ]);

    $this->artisan('masar:reclassify', ['--source' => $keep->source_id])->assertSuccessful();

    expect($a->fresh()->document_type)->toBe(DocumentType::MarketMove)
        ->and($b->fresh()->document_type)->toBe(DocumentType::Other);
});
