<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

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
        $enabled = collect(config('masar.locales'))
            ->filter(fn (array $locale): bool => $locale['enabled'] ?? false)
            ->keys()
            ->all();

        $preferred = $request->getPreferredLanguage($enabled);

        return redirect()->route('web.home', [
            'locale' => $preferred ?: config('masar.default_locale'),
        ], 302);
    }
}
