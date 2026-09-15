<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Newsletter\ConfirmSubscription;
use App\Actions\Newsletter\SubscribeToNewsletter;
use App\Actions\Newsletter\UnsubscribeFromNewsletter;
use App\Http\Requests\SubscribeToNewsletterRequest;
use App\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class NewsletterSubscriptionController
{
    public function store(
        SubscribeToNewsletterRequest $request,
        SubscribeToNewsletter $subscribe,
        string $locale,
    ): RedirectResponse {
        $subscribe(
            email: $request->string('email')->toString(),
            locale: $locale,
            visitorId: $request->attributes->getString('visitor_id') ?: null,
            source: $request->string('source')->toString() ?: null,
        );

        // One sentence for every outcome. See SubscribeToNewsletter.
        return back()->with('newsletter', [
            'ok' => true,
            'source' => $request->string('source')->toString(),
            'message' => 'تفقّد بريدك: أرسلنا رسالة لتأكيد الاشتراك.',
        ])->withFragment('newsletter-heading');
    }

    public function confirm(ConfirmSubscription $confirm, string $locale, Subscriber $subscriber): View
    {
        return view('pages.newsletter-confirmed', ['subscriber' => $confirm($subscriber)]);
    }

    /** A page with a button, not the act itself — see the route comment. */
    public function unsubscribe(string $locale, Subscriber $subscriber): View
    {
        return view('pages.newsletter-unsubscribe', ['subscriber' => $subscriber]);
    }

    public function destroy(UnsubscribeFromNewsletter $unsubscribe, string $locale, Subscriber $subscriber): View
    {
        return view('pages.newsletter-unsubscribed', ['subscriber' => $unsubscribe($subscriber)]);
    }
}
