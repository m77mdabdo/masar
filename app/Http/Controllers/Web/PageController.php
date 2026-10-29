<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Person;
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

    public function corrections(): View
    {
        return view('pages.corrections');
    }

    public function advertise(): View
    {
        return view('pages.advertise');
    }

    /** Legal pages: they describe only processing the code actually does. */
    public function privacy(): View
    {
        return view('pages.legal', ['page' => 'privacy']);
    }

    public function terms(): View
    {
        return view('pages.legal', ['page' => 'terms']);
    }

    public function cookies(): View
    {
        return view('pages.legal', ['page' => 'cookies', 'cookies' => \App\Support\SiteCookies::all()]);
    }

    /**
     * The newsroom page names no one until there is someone real to name.
     *
     * The footer has linked here since the menus were seeded, and it 404'd. The
     * obvious fix — list the eight demo people — would invent a masthead of
     * staff for a real brand, which is the fabrication rule in §5 applied to
     * people rather than quotes. So the page describes how the newsroom works
     * and lists named individuals only when `people.is_expert` holds real,
     * consenting ones. In demo that list is empty and the page says so.
     */
    public function team(): View
    {
        return view('pages.team', [
            'people' => Person::query()
                ->where('is_expert', true)
                ->with('translations')
                ->orderBy('id')
                ->get(),
        ]);
    }
}
