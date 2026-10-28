<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The only unprefixed route. Picks a locale from Accept-Language, considering
 * only locales we actually publish — offering English to a browser that asked
 * for it is worthless if there is no English content behind it.
 */
class RootRedirectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Negotiated on BCP 47 tags, which is what browsers send: `ur-pk` is
        // our URL segment, `ur-PK` is what a phone in Lahore asks for.
        $tags = array_map(Locales::tag(...), Locales::enabled());
        $preferred = $request->getPreferredLanguage($tags);

        return redirect()->route('web.home', [
            'locale' => ($preferred !== null ? Locales::fromTag($preferred) : null) ?? Locales::default(),
        ], 302);
    }
}
