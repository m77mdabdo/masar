<?php

declare(strict_types=1);

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\AuditLog\ActivityResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Countries\CountryResource;
use App\Filament\Resources\Industries\IndustryResource;
use App\Filament\Resources\Markets\MarketResource;
use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Filament\Resources\People\PersonResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Filament\Resources\Topics\TopicResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

/**
 * Every resource page must render for a user who can reach it.
 *
 * Cheap, but it catches the whole class of mistakes that only appear at render
 * time — a bad relationship name, a missing column, a closure with the wrong
 * signature — which unit tests on the model layer never see.
 */
it('registers every expected resource in the panel', function (): void {
    $resources = Filament::getPanel('admin')->getResources();

    expect($resources)->toContain(
        ArticleResource::class,
        CategoryResource::class,
        TopicResource::class,
        CompanyResource::class,
        PersonResource::class,
        IndustryResource::class,
        CountryResource::class,
        MarketResource::class,
        OpportunityResource::class,
        SubscriberResource::class,
        UserResource::class,
        RoleResource::class,
        ActivityResource::class,
    );
});

it('renders every list page', function (string $resource): void {
    $admin = staff('super_admin');
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($admin->fresh())
        ->get($resource::getUrl('index'))
        ->assertOk();
})->with([
    ArticleResource::class,
    CategoryResource::class,
    TopicResource::class,
    CompanyResource::class,
    PersonResource::class,
    IndustryResource::class,
    CountryResource::class,
    MarketResource::class,
    OpportunityResource::class,
    SubscriberResource::class,
    UserResource::class,
    RoleResource::class,
    ActivityResource::class,
]);

it('renders every create page that has one', function (string $resource): void {
    $admin = staff('super_admin');
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($admin->fresh())
        ->get($resource::getUrl('create'))
        ->assertOk();
})->with([
    CategoryResource::class,
    TopicResource::class,
    CompanyResource::class,
    PersonResource::class,
    IndustryResource::class,
    CountryResource::class,
    MarketResource::class,
    OpportunityResource::class,
    UserResource::class,
    RoleResource::class,
]);

it('does not let anyone create a subscriber by hand', function (): void {
    expect(SubscriberResource::canCreate())->toBeFalse();
});

it('keeps the audit log read-only', function (): void {
    $resource = ActivityResource::class;
    $entry = Activity::query()->create(['log_name' => 'test', 'description' => 'x']);

    expect($resource::canCreate())->toBeFalse()
        ->and($resource::canEdit($entry))->toBeFalse()
        ->and($resource::canDelete($entry))->toBeFalse();
});

/**
 * Filament resolves closure parameters by name before falling back to the
 * container. A `modifyQueryUsing(fn (Builder $q) => ...)` does not match the
 * injected `query` argument, so the container builds a fresh model-less Builder
 * and the page dies at render with "… on null".
 *
 * It is silent and easy to reintroduce, so it is asserted rather than trusted.
 */
it('names every filament query closure parameter $query', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path('Filament')),
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        // Only the Filament-evaluated entry points: nested Laravel closures
        // (inside ->when() or ->where()) bind positionally and are fine.
        preg_match_all(
            '/(modifyQueryUsing|->query|query:|true:|false:)\s*\(?\s*fn\s*\(\s*Builder\s+\$(\w+)/',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            if ($match[2] !== 'query') {
                $offenders[] = basename($file->getPathname()).' → $'.$match[2];
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('orders the navigation groups as the newsroom reads them', function (): void {
    $groups = array_map(
        fn ($group): string => $group->getLabel(),
        Filament::getPanel('admin')->getNavigationGroups(),
    );

    // Articles first, always.
    expect($groups)->toBe(['المحتوى', 'الكيانات', 'المنتجات', 'الجمهور', 'النظام']);
});

it('puts every resource in a navigation group', function (): void {
    $ungrouped = [];

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        if (blank($resource::getNavigationGroup())) {
            $ungrouped[] = class_basename($resource);
        }
    }

    expect($ungrouped)->toBe([]);
});
