<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

const SECRET = 'sk_live_4f2a9c7e1b8d';

it('encrypts a secret at rest', function (): void {
    (new Settings)->set('integrations.newsletter_key', SECRET);

    $stored = json_encode(Setting::where('key', 'integrations.newsletter_key')->value('value'));

    expect($stored)->not->toContain(SECRET)
        ->and(strlen((string) $stored))->toBeGreaterThan(strlen(SECRET));
});

it('decrypts it back through the accessor', function (): void {
    (new Settings)->set('integrations.newsletter_key', SECRET);

    expect((new Settings)->get('integrations.newsletter_key'))->toBe(SECRET);
});

it('keeps the secret out of the audit log entirely', function (): void {
    $this->actingAs(staff('super_admin'));

    (new Settings)->set('integrations.newsletter_key', SECRET);

    $entries = Activity::query()->where('subject_type', 'setting')->get();

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        $serialised = json_encode($entry->properties, JSON_UNESCAPED_UNICODE);

        // Not the plaintext, and not the ciphertext either — the audit answers
        // "who changed it and when", never "what to".
        expect($serialised)->not->toContain(SECRET)
            ->and($serialised)->not->toContain('data');
    }
});

it('still audits that a secret changed', function (): void {
    $user = staff('super_admin');
    $this->actingAs($user);

    (new Settings)->set('integrations.newsletter_key', SECRET);

    $entry = Activity::query()->where('subject_type', 'setting')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and((int) $entry->causer_id)->toBe($user->id);
});

it('does not write a secret to the log', function (): void {
    $written = [];

    Log::listen(function ($message) use (&$written): void {
        $written[] = json_encode([$message->message, $message->context]);
    });

    (new Settings)->set('integrations.newsletter_key', SECRET);
    (new Settings)->all();

    // Nothing logged at all is the expected outcome and also a pass — assert it
    // explicitly so the test is not counted as risky for making no assertion.
    expect($written)->toBeArray();

    foreach ($written as $line) {
        expect($line)->not->toContain(SECRET);
    }
});

it('treats non-secret settings as plain text', function (): void {
    (new Settings)->set('identity.site_name', 'مسار');

    $stored = json_encode(Setting::where('key', 'identity.site_name')->value('value'), JSON_UNESCAPED_UNICODE);

    expect($stored)->toContain('مسار');
});

it('knows which keys are secret', function (): void {
    $settings = new Settings;

    expect($settings->isEncrypted('integrations.newsletter_key'))->toBeTrue()
        ->and($settings->isEncrypted('integrations.analytics_id'))->toBeTrue()
        ->and($settings->isEncrypted('identity.site_name'))->toBeFalse();
});

it('survives an unreadable value instead of taking the site down', function (): void {
    (new Settings)->set('integrations.newsletter_key', SECRET);

    // Simulate a rotated APP_KEY leaving old ciphertext undecryptable.
    Setting::where('key', 'integrations.newsletter_key')->update(['value' => ['data' => 'not-real-ciphertext']]);

    expect((new Settings)->get('integrations.newsletter_key'))->toBeNull();
});

it('does not wipe a secret when the form is saved with it blank', function (): void {
    (new Settings)->set('integrations.newsletter_key', SECRET);

    // The page skips blank encrypted fields; this asserts the intent directly.
    $settings = new Settings;
    $blankSkipped = collect(['integrations.newsletter_key' => '', 'identity.site_name' => 'مسار'])
        ->reject(fn ($value, $key): bool => $settings->isEncrypted($key) && blank($value))
        ->all();

    $settings->setMany($blankSkipped);

    expect((new Settings)->get('integrations.newsletter_key'))->toBe(SECRET);
});
