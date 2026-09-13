<?php

declare(strict_types=1);

use App\Actions\Intelligence\RecordSourceResult;
use App\Models\Source;
use App\Models\User;
use App\Notifications\SourceDisabled;
use App\Services\Intelligence\FetchResult;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

function chiefEditor(): User
{
    Role::findOrCreate('editor_in_chief', 'web');
    $user = User::factory()->create();
    $user->assignRole('editor_in_chief');

    return $user;
}

it('counts failures and keeps the source active below the threshold', function (): void {
    $source = Source::factory()->create();
    $record = app(RecordSourceResult::class);

    foreach (range(1, 4) as $_) {
        $record($source->refresh(), FetchResult::failed('http 500'));
    }

    expect($source->refresh()->consecutive_failures)->toBe(4)
        ->and($source->is_active)->toBeTrue()
        ->and($source->healthState())->toBe('failing');
});

it('disables the source at the threshold and notifies someone who can act', function (): void {
    Notification::fake();
    $chief = chiefEditor();

    $source = Source::factory()->create();
    $record = app(RecordSourceResult::class);

    foreach (range(1, (int) config('masar.intelligence.failure_threshold')) as $_) {
        $record($source->refresh(), FetchResult::failed('http 500'));
    }

    expect($source->refresh()->is_active)->toBeFalse()
        ->and($source->healthState())->toBe('disabled');

    // A source disabled silently is the failure this whole subsystem exists to
    // avoid, not one it is allowed to create.
    Notification::assertSentTo($chief, SourceDisabled::class);
});

it('notifies once, not on every failure after the threshold', function (): void {
    Notification::fake();
    chiefEditor();

    $source = Source::factory()->create();
    $record = app(RecordSourceResult::class);

    foreach (range(1, 8) as $_) {
        $record($source->refresh(), FetchResult::failed('http 500'));
    }

    Notification::assertSentTimes(SourceDisabled::class, 1);
});

it('resets the streak on a success', function (): void {
    $source = Source::factory()->failing(3)->create();

    app(RecordSourceResult::class)($source, FetchResult::ok([]), 0);

    expect($source->refresh()->consecutive_failures)->toBe(0)
        ->and($source->error_message)->toBeNull()
        ->and($source->last_success_at)->not->toBeNull();
});

it('treats a 304 as a healthy answer', function (): void {
    $source = Source::factory()->failing(2)->create();

    app(RecordSourceResult::class)($source, FetchResult::notModified());

    // Conflating "nothing new" with "broken" would disable a working source
    // that simply had a quiet day.
    expect($source->refresh()->consecutive_failures)->toBe(0)
        ->and($source->healthState())->toBe('healthy');
});

it('backs off further on each failure and obeys Retry-After over its own arithmetic', function (): void {
    $source = Source::factory()->create(['poll_frequency_minutes' => 10]);
    $record = app(RecordSourceResult::class);

    $record($source, FetchResult::failed('http 500'));
    $first = $source->refresh()->last_checked_at;

    $record($source, FetchResult::failed('http 500'));
    $second = $source->refresh()->last_checked_at;

    expect($second->greaterThan($first))->toBeTrue();

    $record($source, FetchResult::failed('rate limited', retryAfterSeconds: 7200));

    // A publisher's instruction beats our exponent.
    expect($source->refresh()->last_checked_at->greaterThan($second))->toBeTrue();
});

it('reports a source that answers but has published nothing for a week', function (): void {
    $source = Source::factory()->create([
        'last_success_at' => now(),
        'last_item_at' => now()->subDays(10),
    ]);

    // Looks perfectly healthy by every other measure, and has usually moved.
    expect($source->healthState())->toBe('quiet');
});

it('only polls sources that are due', function (): void {
    Source::factory()->create(['last_checked_at' => null]);
    Source::factory()->create(['last_checked_at' => now()->subMinutes(60), 'poll_frequency_minutes' => 30]);
    Source::factory()->create(['last_checked_at' => now(), 'poll_frequency_minutes' => 30]);
    Source::factory()->create(['last_checked_at' => null, 'is_active' => false]);

    expect(Source::query()->due()->count())->toBe(2);
});
