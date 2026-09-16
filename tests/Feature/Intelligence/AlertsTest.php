<?php

declare(strict_types=1);

use App\Actions\Intelligence\AlertOnHighImportance;
use App\Models\IntelligenceItem;
use App\Notifications\HighImportanceItem;
use Illuminate\Support\Facades\Notification;

/**
 * The inbox is a queue an editor clears on their own rhythm. An alert is for
 * the cases where that rhythm is too slow.
 *
 * Every test here is about *not* firing, because the failure mode of an alert
 * is not silence — it is noise. A channel that fires constantly gets muted, and
 * a muted alert is worse than none because everyone believes it works.
 */
beforeEach(function (): void {
    Notification::fake();
    staff('editor_in_chief');
});

it('alerts on an item above the threshold', function (): void {
    $item = IntelligenceItem::factory()->create([
        'importance_base' => 90,
        'published_at' => now(),
        'duplicate_of_id' => null,
    ]);

    expect(app(AlertOnHighImportance::class)($item))->toBeTrue();

    Notification::assertSentTimes(HighImportanceItem::class, 1);
});

it('stays quiet below the threshold', function (): void {
    $item = IntelligenceItem::factory()->create([
        'importance_base' => 20,
        'published_at' => now(),
    ]);

    expect(app(AlertOnHighImportance::class)($item))->toBeFalse();

    Notification::assertNothingSent();
});

it('never alerts on a duplicate', function (): void {
    // The story already arrived. Telling someone twice about one event is the
    // fastest way to get the channel muted.
    $original = IntelligenceItem::factory()->create(['importance_base' => 90, 'published_at' => now()]);
    $duplicate = IntelligenceItem::factory()->create([
        'importance_base' => 90,
        'published_at' => now(),
        'duplicate_of_id' => $original->getKey(),
    ]);

    expect(app(AlertOnHighImportance::class)($duplicate))->toBeFalse();

    Notification::assertNothingSent();
});

it('can be switched off entirely without deleting code', function (): void {
    config(['masar.intelligence.alert_importance' => null]);

    $item = IntelligenceItem::factory()->create(['importance_base' => 99, 'published_at' => now()]);

    expect(app(AlertOnHighImportance::class)($item))->toBeFalse();

    Notification::assertNothingSent();
});

it('sends to people who can act, and nobody else', function (): void {
    $writer = staff('writer');
    $chief = staff('editor_in_chief');

    $item = IntelligenceItem::factory()->create(['importance_base' => 95, 'published_at' => now()]);
    app(AlertOnHighImportance::class)($item);

    Notification::assertSentTo($chief, HighImportanceItem::class);
    Notification::assertNotSentTo($writer, HighImportanceItem::class);
});

it('reaches the in-app centre as well as email', function (): void {
    // Database-backed so a notice survives the editor not being logged in when
    // it was raised — a source dies at 3am, the person who can fix it arrives
    // at 9.
    $item = IntelligenceItem::factory()->create(['importance_base' => 95, 'published_at' => now()]);

    app(AlertOnHighImportance::class)($item);

    Notification::assertSentTo(
        App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'editor_in_chief'))->first(),
        HighImportanceItem::class,
        fn (HighImportanceItem $n, array $channels): bool => in_array('database', $channels, true)
            && in_array('mail', $channels, true),
    );
});

it('carries no publisher prose into the notification', function (): void {
    // Raw text is internal for its whole life, and does not travel into an
    // email either.
    $item = IntelligenceItem::factory()->create([
        'importance_base' => 95,
        'published_at' => now(),
        'title' => 'قرار تنظيمي جديد',
    ]);

    $payload = (new HighImportanceItem($item))->toArray($item);

    expect($payload)->toHaveKeys(['title', 'body', 'importance', 'intelligence_item_id'])
        ->and($payload)->not->toHaveKey('raw_body');
});
