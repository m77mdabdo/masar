<?php

declare(strict_types=1);

use App\Actions\Redirects\NormalisePath;
use App\Filament\Pages\NotFoundLog;
use App\Jobs\RecordNotFound;
use App\Models\NotFound;
use App\Models\Redirect;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

function recordMiss(string $path, ?string $referrer = null): void
{
    app(RecordNotFound::class, ['path' => $path, 'referrer' => $referrer])
        ->handle(app(NormalisePath::class));
}

it('records a missing path', function (): void {
    recordMiss('/ar/dead-link');

    expect(NotFound::where('path', '/ar/dead-link')->exists())->toBeTrue();
});

it('aggregates repeat hits into one row', function (): void {
    foreach (range(1, 5) as $ignored) {
        recordMiss('/ar/dead-link');
    }

    // A row per hit would bury the real dead links under scanner noise.
    expect(NotFound::where('path', '/ar/dead-link')->count())->toBe(1)
        ->and(NotFound::where('path', '/ar/dead-link')->value('hits'))->toBe(5);
});

it('treats path variants as the same dead link', function (): void {
    recordMiss('/ar/dead-link');
    recordMiss('/ar/dead-link/');
    recordMiss('/ar/dead-link?utm_source=x');

    expect(NotFound::count())->toBe(1)
        ->and(NotFound::first()->hits)->toBe(3);
});

it('moves last_seen_at forward on a repeat hit', function (): void {
    recordMiss('/ar/dead-link');
    $first = NotFound::first()->last_seen_at;

    $this->travelTo(now()->addHours(3));
    recordMiss('/ar/dead-link');
    $this->travelBack();

    expect(NotFound::first()->last_seen_at->timestamp)->toBeGreaterThan($first->timestamp);
});

it('ignores obvious bot probes', function (string $path): void {
    recordMiss($path);

    expect(NotFound::where('path', 'like', '%'.ltrim($path, '/').'%')->exists())->toBeFalse();
})->with([
    '/wp-admin',
    '/wp-login.php',
    '/index.php',
    '/.env',
    '/.git/config',
    '/vendor/phpunit/phpunit/phpunit.php',
    '/phpmyadmin/index.php',
    '/shell.aspx',
]);

it('still records a genuine dead article url', function (): void {
    recordMiss('/ar/saudi/an-article-that-moved');

    expect(NotFound::count())->toBe(1);
});

it('ignores the root path', function (): void {
    recordMiss('/');

    expect(NotFound::count())->toBe(0);
});

it('queues rather than logging inline', function (): void {
    Queue::fake();

    $this->get('/ar/definitely-missing')->assertNotFound();

    // A 404 is already a bad experience; it must not also be a slow one.
    Queue::assertPushed(RecordNotFound::class);
});

it('does not queue a job for a bot probe', function (): void {
    Queue::fake();

    $this->get('/wp-login.php');

    Queue::assertNotPushed(RecordNotFound::class);
});

it('renders the log page', function (): void {
    NotFound::factory()->count(3)->create();

    $admin = staff('super_admin');
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->actingAs($admin->fresh());

    livewire(NotFoundLog::class)->assertOk();
});

it('creates a working redirect from a logged 404 and resolves the row', function (): void {
    $miss = NotFound::factory()->create(['path' => '/ar/moved-story', 'resolved' => false]);

    $admin = staff('super_admin');
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->actingAs($admin->fresh());

    livewire(NotFoundLog::class)
        ->callAction(
            TestAction::make('createRedirect')->table($miss),
            ['from_path' => '/ar/moved-story', 'to_path' => '/ar/saudi/new-home', 'status_code' => 301],
        );

    expect(Redirect::where('from_path', '/ar/moved-story')->exists())->toBeTrue()
        ->and($miss->fresh()->resolved)->toBeTrue();

    // And it actually works.
    $this->get('/ar/moved-story')->assertRedirect('/ar/saudi/new-home');
});

it('does not resolve the row when the redirect is rejected', function (): void {
    Redirect::create(['from_path' => '/ar/a', 'to_path' => '/ar/b', 'status_code' => 301]);
    $miss = NotFound::factory()->create(['path' => '/ar/b', 'resolved' => false]);

    $admin = staff('super_admin');
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->actingAs($admin->fresh());

    // /ar/b → /ar/a would be a loop; the row must stay open.
    livewire(NotFoundLog::class)
        ->callAction(
            TestAction::make('createRedirect')->table($miss),
            ['from_path' => '/ar/b', 'to_path' => '/ar/a', 'status_code' => 301],
        );

    expect($miss->fresh()->resolved)->toBeFalse()
        ->and(Redirect::where('from_path', '/ar/b')->exists())->toBeFalse();
});

it('keeps the page behind the settings permission', function (): void {
    $this->actingAs(staff('writer'))
        ->get(NotFoundLog::getUrl())
        ->assertForbidden();
});
