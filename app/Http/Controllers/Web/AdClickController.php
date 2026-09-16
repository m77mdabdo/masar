<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\AdCreative;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Counts a click and forwards to the advertiser.
 *
 * Server-side and scriptless: no tracking pixel, no third-party beacon, nothing
 * that runs before the reader has chosen to go. The redirect also means the
 * advertiser sees our domain as the referrer rather than the article the reader
 * was on, which is theirs to be told only if we decide to tell them.
 */
class AdClickController
{
    public function __invoke(string $locale, AdCreative $creative): RedirectResponse
    {
        // An expired campaign's link is dead, not merely uncounted. A creative
        // that stopped being servable stops being followable.
        if (! $creative->is_active || ! $creative->campaign?->isRunning()) {
            throw new NotFoundHttpException;
        }

        $creative->incrementQuietly('clicks');

        return redirect()->away($creative->destination_url, 302);
    }
}
