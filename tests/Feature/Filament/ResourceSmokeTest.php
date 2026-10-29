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

/*
 * The same trap for a record closure: `getOptionLabelFromRecordUsing(fn ($r) …)`
 * receives null, because Filament resolves the parameter by name. The smoke
 * test above cannot see it — with no related rows the closure never runs —
 * and three resources returned 500 on real data until the admin browser audit
 * of 2026-10-29 loaded them.
 */
it('names every filament-evaluated closure parameter with a name filament injects', function (): void {
    // A typed enum or model parameter under an unknown name is resolved from
    // the container instead: an enum throws "not instantiable", a model
    // arrives empty. /admin/companies and /admin/opportunities returned 500
    // on real data this way, past every smoke test.
    $injected = ['record', 'state', 'query', 'get', 'set', 'livewire', 'component', 'column', 'operation', 'model', 'data', 'value', 'search', 'rule', 'records', 'action', 'arguments', 'table', 'context'];
    $methods = 'getOptionLabelFromRecordUsing|getStateUsing|recordUrl|formatStateUsing|color|icon|iconColor|description|tooltip|badge|visible|hidden|url|disabled|label|helperText|hint|placeholder|default|afterStateUpdated|dehydrateStateUsing|formatStateUsing|getSearchResultsUsing|options';
    $offenders = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Filament'))) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        preg_match_all('/->('.$methods.')\s*\(\s*(?:static\s+)?fn\s*\(([^)]*)\)/', (string) file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            preg_match_all('/\$(\w+)/', $match[2], $params);
            foreach ($params[1] as $name) {
                if (! in_array($name, $injected, true)) {
                    $offenders[] = basename($file->getPathname()).' '.$match[1].' → $'.$name;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
