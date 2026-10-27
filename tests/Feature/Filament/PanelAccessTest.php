<?php

declare(strict_types=1);

use App\Filament\Resources\Subscribers\Pages\ListSubscribers;
use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Filament\Resources\Topics\TopicResource;
use App\Models\Subscriber;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Livewire\livewire;

/**
 * Filament allows anything it cannot resolve a policy for. A resource with no
 * policy and no canAccess() is not locked down by default — it is open to every
 * user who can reach the panel, silently, with no error anywhere.
 *
 * Seven resources were in exactly that state, the subscriber list among them.
 * This is the invariant that stops the eighth.
 */
it('gates every Filament resource on a policy or an explicit access check', function (): void {
    $ungated = [];

    // Both depths: a resource dropped straight into Filament/Resources/ is as
    // open as one in a subdirectory, and an invariant that only walks the
    // layout we happen to use today is a list wearing a glob's clothes.
    $files = array_merge(
        glob(app_path('Filament/Resources/*Resource.php')) ?: [],
        glob(app_path('Filament/Resources/*/*Resource.php')) ?: [],
    );

    foreach ($files as $file) {
        $dir = basename(dirname($file));
        $class = $dir === 'Resources'
            ? 'App\\Filament\\Resources\\'.basename($file, '.php')
            : 'App\\Filament\\Resources\\'.$dir.'\\'.basename($file, '.php');

        if (! class_exists($class) || ! is_subclass_of($class, Resource::class)) {
            continue;
        }

        // Either entry point counts: canAccess() gates the whole resource,
        // canViewAny() gates the listing Filament routes to.
        $declaresOwnCheck = collect(['canAccess', 'canViewAny', 'canView'])->contains(
            fn (string $method): bool => method_exists($class, $method)
                && (new ReflectionMethod($class, $method))->getDeclaringClass()->getName() === $class,
        );

        $model = $class::getModel();
        $hasPolicy = Gate::getPolicyFor($model) !== null;

        if (! $declaresOwnCheck && ! $hasPolicy) {
            $ungated[] = class_basename($class).' ('.class_basename($model).')';
        }
    }

    expect($ungated)->toBe([], 'Ungated Filament resources: '.implode(', ', $ungated));
});

/**
 * A Filament Page has no model and so no policy to fall back on: one with no
 * canAccess() is reachable by every user who can open the panel. The dashboard
 * carried no check for the life of the project — harmless only because each of
 * its widgets gated itself, which is a property of today's six widgets rather
 * than of the page.
 */
it('gates every Filament page on its own access check', function (): void {
    $ungated = [];

    foreach (glob(app_path('Filament/Pages/*.php')) ?: [] as $file) {
        $class = 'App\\Filament\\Pages\\'.basename($file, '.php');

        if (! class_exists($class)) {
            continue;
        }

        $declaresOwnCheck = method_exists($class, 'canAccess')
            && (new ReflectionMethod($class, 'canAccess'))->getDeclaringClass()->getName() === $class;

        if (! $declaresOwnCheck) {
            $ungated[] = class_basename($class);
        }
    }

    expect($ungated)->toBe([], 'Ungated Filament pages: '.implode(', ', $ungated));
});

it('keeps the subscriber list away from a fact-checker', function (): void {
    Subscriber::factory()->count(3)->create();

    $this->actingAs(staff('fact_checker'));

    expect(SubscriberResource::canAccess())->toBeFalse();

    livewire(ListSubscribers::class)->assertStatus(Response::HTTP_FORBIDDEN);
});

it('keeps the subscriber list away from a writer', function (): void {
    $this->actingAs(staff('writer'));

    expect(SubscriberResource::canAccess())->toBeFalse();
});

/**
 * The export bulk action hands the whole list over as a file, so the read gate
 * is the only thing standing in front of it.
 */
it('keeps the subscriber list away from an editor without settings rights', function (): void {
    $this->actingAs(staff('editor'));

    expect(SubscriberResource::canAccess())->toBeFalse();
});

it('gives the subscriber list to the editor in chief', function (): void {
    Subscriber::factory()->count(3)->create();

    $this->actingAs(staff('editor_in_chief'));

    expect(SubscriberResource::canAccess())->toBeTrue();

    livewire(ListSubscribers::class)->assertOk();
});

it('never lets anyone edit a subscriber by hand', function (): void {
    $subscriber = Subscriber::factory()->create();

    foreach (['super_admin', 'editor_in_chief'] as $role) {
        $user = staff($role);

        expect($user->can('update', $subscriber))->toBeFalse("{$role} may update")
            ->and($user->can('delete', $subscriber))->toBeFalse("{$role} may delete")
            ->and($user->can('create', Subscriber::class))->toBeFalse("{$role} may create");
    }
});

it('holds the taxonomy at the same bar as editing anyone\'s article', function (): void {
    $this->actingAs(staff('writer'));
    expect(TopicResource::canAccess())->toBeFalse();

    $this->actingAs(staff('editor'));
    expect(TopicResource::canAccess())->toBeTrue();
});
