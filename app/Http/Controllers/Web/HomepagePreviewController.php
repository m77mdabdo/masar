<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\HomepageLayout;
use App\Queries\ComposeHomepage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomepagePreviewController
{
    public function __invoke(Request $request, HomepageLayout $layout, ComposeHomepage $compose): View
    {
        abort_unless($request->user()?->can('homepage.manage') ?? false, 403);

        return view('filament.pages.homepage-preview', [
            'layout' => $layout,
            // The edition the editor works in: an English panel previews the English front page.
            'sections' => $compose($layout, \App\Support\Locales::exists(app()->getLocale()) ? app()->getLocale() : \App\Support\Locales::default()),
        ]);
    }
}
