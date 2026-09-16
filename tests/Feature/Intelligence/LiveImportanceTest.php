<?php

declare(strict_types=1);

use App\Models\IntelligenceItem;

/**
 * Importance has a component that decays, so storing it produces a number that
 * is wrong the moment it is written. A third of the corpus drifted in a day,
 * and the inbox's "most important first" quietly stopped meaning anything: the
 * recency weight varied only by how stale a feed's own listing had been, never
 * by how old the item was when an editor looked at it.
 *
 * `importance_base` keeps the three durable inputs; recency is added back
 * against the clock, in PHP for display and in SQL for ordering.
 */
it('drops an item down the ranking as it ages, without anything rewriting it', function (): void {
    $item = IntelligenceItem::factory()->create([
        'importance_base' => 60,
        'importance' => 75,
        'published_at' => now(),
    ]);

    $fresh = $item->liveImportance();

    // Nothing writes to the row. Only the clock moves.
    $this->travel(40)->hours();

    expect($item->fresh()->liveImportance())->toBeLessThan($fresh);
});

it('never lets recency go negative past the window', function (): void {
    $item = IntelligenceItem::factory()->create([
        'importance_base' => 60,
        'published_at' => now()->subDays(30),
    ]);

    // Floored at the base: an old item is not worth less than its own merits.
    expect($item->liveImportance())->toBe(60);
});

it('treats a missing publication date as unknown rather than old', function (): void {
    $dated = IntelligenceItem::factory()->create(['importance_base' => 60, 'published_at' => now()->subDays(30)]);
    $undated = IntelligenceItem::factory()->create(['importance_base' => 60, 'published_at' => null]);

    expect($undated->liveImportance())->toBeGreaterThan($dated->liveImportance());
});

it('falls back to the stored snapshot for a row never re-derived', function (): void {
    $item = IntelligenceItem::factory()->create([
        'importance_base' => null,
        'importance' => 42,
    ]);

    expect($item->liveImportance())->toBe(42);
});

it('orders identically in SQL and in PHP', function (): void {
    /*
     * The invariant that matters, and it caught three real faults: SQL
     * truncates minutes with TIMESTAMPDIFF while PHP was using fractional
     * ones; PHP called now() per item so items compared microseconds apart got
     * different ages; and ties had no deterministic tiebreak, which on a
     * paginated query shows a row on two pages and skips another.
     */
    foreach (range(1, 12) as $i) {
        IntelligenceItem::factory()->create([
            'importance_base' => 50 + ($i % 4),
            'published_at' => now()->subMinutes($i * 37),
        ]);
    }

    $now = now();

    $sql = IntelligenceItem::query()->orderByLiveImportance()->pluck('id')->all();
    $php = IntelligenceItem::all()
        ->sort(fn ($a, $b) => [$b->liveImportanceScore($now), $b->detected_at->timestamp, $b->id]
                          <=> [$a->liveImportanceScore($now), $a->detected_at->timestamp, $a->id])
        ->pluck('id')->all();

    expect($sql)->toBe($php);
});

it('breaks ties deterministically, so pagination cannot drop a row', function (): void {
    // Identical on every scoring input.
    $items = collect(range(1, 6))->map(fn (): IntelligenceItem => IntelligenceItem::factory()->create([
        'importance_base' => 55,
        'published_at' => now()->subHour(),
        'detected_at' => now()->subHour(),
    ]));

    $first = IntelligenceItem::query()->orderByLiveImportance()->pluck('id')->all();
    $second = IntelligenceItem::query()->orderByLiveImportance()->pluck('id')->all();

    expect($first)->toBe($second)
        ->and($first)->toHaveCount($items->count());
});

it('keeps the stored snapshot for audit rather than overwriting it', function (): void {
    // `importance` is what we thought at ingest — the number an editor saw.
    $item = IntelligenceItem::factory()->create([
        'importance' => 75,
        'importance_base' => 60,
        'published_at' => now()->subDays(10),
    ]);

    expect($item->importance)->toBe(75)
        ->and($item->liveImportance())->toBe(60);
});
