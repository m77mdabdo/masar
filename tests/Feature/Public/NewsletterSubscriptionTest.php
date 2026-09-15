<?php

declare(strict_types=1);

use App\Enums\SubscriberStatus;
use App\Http\Middleware\IssueVisitorId;
use App\Http\Requests\SubscribeToNewsletterRequest;
use App\Models\Subscriber;
use App\Notifications\ConfirmNewsletterSubscription;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The newsletter is the only write a reader can make, and for months the form
 * rendered as `method="GET"` with no action — it looked finished, submitted
 * nowhere, and stored nothing. That failure mode is silence, so these tests
 * assert the mechanism and not only the outcome (CLAUDE.md §7).
 */
beforeEach(function (): void {
    Notification::fake();
});

it('renders a form that actually posts to the subscribe route', function (string $path): void {
    // The regression guard. A form that reverts to GET passes every test that
    // only checks the page renders, which is exactly how this shipped broken.
    $html = $this->get($path)->assertOk()->getContent();
    $action = route('web.newsletter.subscribe', 'ar');

    expect($html)
        ->toContain('method="POST"')
        ->toContain('action="'.e($action).'"')
        ->toContain('name="_token"');
})->with(['/ar', '/ar/newsletter']);

it('stores a pending subscriber and sends one confirmation', function (): void {
    $this->post(route('web.newsletter.subscribe', 'ar'), [
        'email' => 'Reader@Example.COM',
        'source' => 'band',
    ])->assertRedirect();

    $subscriber = Subscriber::query()->sole();

    // Stored lowercased, because uniqueness is compared on it.
    expect($subscriber->email)->toBe('reader@example.com')
        ->and($subscriber->status)->toBe(SubscriberStatus::Pending)
        ->and($subscriber->verified_at)->toBeNull()
        ->and($subscriber->confirmation_sent_at)->not->toBeNull();

    Notification::assertSentTo($subscriber, ConfirmNewsletterSubscription::class);
});

it('records the visitor id so returning audience is measurable', function (): void {
    // The KPI. A subscriber with no visitor id cannot be tied to a return
    // visit, and the first request a visitor makes is often the POST itself.
    $this->post(route('web.newsletter.subscribe', 'ar'), ['email' => 'a@example.com']);

    expect(Subscriber::query()->sole()->visitor_id)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

it('reuses the visitor id already in the jar', function (): void {
    $this->withUnencryptedCookie(IssueVisitorId::COOKIE, '01HZZZZZZZZZZZZZZZZZZZZZZZ')
        ->post(route('web.newsletter.subscribe', 'ar'), ['email' => 'a@example.com']);

    expect(Subscriber::query()->sole()->visitor_id)->toBe('01HZZZZZZZZZZZZZZZZZZZZZZZ');
});

it('refuses a malformed email', function (): void {
    $this->post(route('web.newsletter.subscribe', 'ar'), ['email' => 'not-an-address'])
        ->assertSessionHasErrors('email');

    expect(Subscriber::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('drops a submission that filled the honeypot', function (): void {
    $this->post(route('web.newsletter.subscribe', 'ar'), [
        'email' => 'bot@example.com',
        SubscribeToNewsletterRequest::HONEYPOT => 'https://spam.example',
    ])->assertSessionHasErrors(SubscribeToNewsletterRequest::HONEYPOT);

    expect(Subscriber::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('answers a known address exactly as it answers a new one', function (): void {
    // Anything that distinguishes the two turns the form into an oracle for
    // "does this person read MASAR", which is not ours to disclose.
    $existing = Subscriber::factory()->confirmed()->create(['email' => 'known@example.com']);

    $new = $this->post(route('web.newsletter.subscribe', 'ar'), ['email' => 'fresh@example.com']);
    $known = $this->post(route('web.newsletter.subscribe', 'ar'), ['email' => 'known@example.com']);

    expect($known->status())->toBe($new->status())
        ->and($known->headers->get('Location'))->toBe($new->headers->get('Location'))
        ->and(session('newsletter')['message'])->toBe($new->getSession()->get('newsletter')['message']);

    // And the confirmed reader is not mailed again.
    Notification::assertNotSentTo($existing, ConfirmNewsletterSubscription::class);
});

it('will not send a second confirmation inside the cooling-off window', function (): void {
    // Otherwise the form is a way to post mail to an address you do not own.
    $subscriber = Subscriber::factory()->create([
        'email' => 'target@example.com',
        'status' => SubscriberStatus::Pending,
        'confirmation_sent_at' => now()->subMinutes(2),
    ]);

    $this->post(route('web.newsletter.subscribe', 'ar'), ['email' => 'target@example.com']);

    Notification::assertNothingSent();

    $this->travel(Subscriber::RESEND_AFTER_MINUTES + 1)->minutes();
    $this->post(route('web.newsletter.subscribe', 'ar'), ['email' => 'target@example.com']);

    Notification::assertSentTo($subscriber, ConfirmNewsletterSubscription::class);
});

it('returns someone who left to pending rather than straight back on the list', function (): void {
    $subscriber = Subscriber::factory()->unsubscribed()->create(['email' => 'back@example.com']);

    $this->post(route('web.newsletter.subscribe', 'ar'), ['email' => 'back@example.com']);

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Pending)
        ->and($subscriber->fresh()->unsubscribed_at)->toBeNull();
});

it('confirms a subscription from the signed link', function (): void {
    $subscriber = Subscriber::factory()->create(['status' => SubscriberStatus::Pending]);

    $this->get($subscriber->confirmationUrl())->assertOk();

    $subscriber->refresh();
    expect($subscriber->status)->toBe(SubscriberStatus::Confirmed)
        ->and($subscriber->verified_at)->not->toBeNull()
        ->and($subscriber->isConfirmed())->toBeTrue();
});

it('rejects a confirm link that was tampered with', function (): void {
    $subscriber = Subscriber::factory()->create(['status' => SubscriberStatus::Pending]);
    $other = Subscriber::factory()->create(['status' => SubscriberStatus::Pending]);

    // Swapping the id in a signed URL must not confirm someone else's address.
    $forged = str_replace(
        '/'.$subscriber->getKey().'?',
        '/'.$other->getKey().'?',
        $subscriber->confirmationUrl(),
    );

    $this->get($forged)->assertForbidden();
    expect($other->fresh()->status)->toBe(SubscriberStatus::Pending);
});

it('rejects a confirm link after it expires', function (): void {
    $subscriber = Subscriber::factory()->create(['status' => SubscriberStatus::Pending]);
    $url = $subscriber->confirmationUrl();

    $this->travel(Subscriber::CONFIRMATION_TTL_DAYS + 1)->days();

    $this->get($url)->assertForbidden();
    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Pending);
});

it('does not unsubscribe anyone on a GET', function (): void {
    // Mail clients and scanners fetch every link before a human sees it.
    $subscriber = Subscriber::factory()->confirmed()->create();

    $this->get($subscriber->unsubscribeUrl())->assertOk();

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Confirmed);
});

it('unsubscribes on the posted confirmation', function (): void {
    $subscriber = Subscriber::factory()->confirmed()->create();

    $this->post($subscriber->unsubscribeUrl())->assertOk();

    $subscriber->refresh();
    expect($subscriber->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->unsubscribed_at)->not->toBeNull()
        // The row survives, so a later import cannot quietly re-add them.
        ->and(Subscriber::query()->whereKey($subscriber->getKey())->exists())->toBeTrue();
});

it('keeps an unsubscribed reader out of the confirmed scope', function (): void {
    Subscriber::factory()->confirmed()->create();
    $gone = Subscriber::factory()->confirmed()->create();

    $this->post($gone->unsubscribeUrl())->assertOk();

    expect(Subscriber::query()->confirmed()->count())->toBe(1);
});

it('rate limits the subscribe route', function (): void {
    // A safety control, not a capacity one: each accepted request can cause
    // mail to be sent to a third party.
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('web.newsletter.subscribe', 'ar'), ['email' => "r{$i}@example.com"])
            ->assertRedirect();
    }

    $this->post(route('web.newsletter.subscribe', 'ar'), ['email' => 'over@example.com'])
        ->assertStatus(429);
});
