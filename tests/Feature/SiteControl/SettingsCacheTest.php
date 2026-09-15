<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

function settings(): Settings
{
    // A fresh instance per assertion: the singleton memoises within a request,
    // which is the behaviour under test in some of these and noise in others.
    return new Settings;
}

it('falls back to config defaults before anything is saved', function (): void {
    expect(setting('identity.site_name'))->toBe('مسار')
        ->and(setting('editorial.articles_per_page'))->toBe(12);
});

it('returns a saved value over the default', function (): void {
    settings()->set('identity.site_name', 'مسار الأعمال');

    expect(settings()->get('identity.site_name'))->toBe('مسار الأعمال');
});

it('reads the whole table once and caches it', function (): void {
    settings()->set('identity.tagline', 'من المعلومة إلى الفرصة');

    $instance = settings();
    $instance->all();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    // Twenty reads across a page render must not be twenty queries.
    foreach (range(1, 20) as $ignored) {
        $instance->get('identity.tagline');
        $instance->get('identity.site_name');
    }

    expect($queries)->toBe(0);
});

it('flushes the cache on save', function (): void {
    settings()->set('identity.site_name', 'أول');
    expect(settings()->get('identity.site_name'))->toBe('أول');

    settings()->set('identity.site_name', 'ثانٍ');

    // A stale name after an edit makes the owner edit it again and file a bug.
    expect(settings()->get('identity.site_name'))->toBe('ثانٍ');
});

it('writes an audit entry when a setting changes', function (): void {
    $user = staff('super_admin');
    $this->actingAs($user);

    settings()->set('identity.site_name', 'اسم جديد');

    $entry = Activity::query()
        ->where('subject_type', 'setting')
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull()
        ->and((int) $entry->causer_id)->toBe($user->id);
});

it('groups a setting by its namespace', function (): void {
    settings()->set('integrations.newsletter_key', 'nk-XXXX');

    expect(Setting::where('key', 'integrations.newsletter_key')->value('group'))->toBe('integrations');
});

it('updates rather than duplicating an existing key', function (): void {
    settings()->set('identity.tagline', 'أول');
    settings()->set('identity.tagline', 'ثانٍ');

    expect(Setting::where('key', 'identity.tagline')->count())->toBe(1);
});

it('stores structured values intact', function (): void {
    settings()->setMany([
        'contact.social' => ['x' => 'https://x.com/masar', 'linkedin' => 'https://linkedin.com/company/masar'],
        'maintenance.allowlist' => ['127.0.0.1', '10.0.0.1'],
    ]);

    expect(settings()->get('contact.social'))->toBe(['x' => 'https://x.com/masar', 'linkedin' => 'https://linkedin.com/company/masar'])
        ->and(settings()->get('maintenance.allowlist'))->toBe(['127.0.0.1', '10.0.0.1']);
});
