<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Contracts\View\View;

/**
 * Static editorial pages. Their copy lives in Blade rather than the database
 * because it changes about once a year and belongs in version control.
 */
class PageController
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function editorialStandards(): View
    {
        return view('pages.editorial-standards');
    }
}
