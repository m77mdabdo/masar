<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Person;
use App\Queries\EntityContentQuery;
use App\Queries\EntityProfileQuery;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PersonController
{
    public function __invoke(string $locale, string $slug): View
    {
        $person = Person::query()
            ->where('slug', $slug)
            ->with(['translations', 'company.translations'])
            ->first();

        if ($person === null) {
            throw new NotFoundHttpException;
        }

        /*
         * The same graph questions as a company, where they earn their place.
         * For a person the useful ones are what they are quoted on and who they
         * appear beside — the coverage-shape breakdown that suits a company
         * does not say much about an individual, so it is not asked here.
         */
        $profile = EntityProfileQuery::for($person);

        return view('pages.person', [
            'person' => $person,
            'sections' => $profile->sections(),
            'coMentions' => $profile->coMentions(perType: 5),
            'mentions' => EntityContentQuery::for($person)->inLocale($locale)->paginate(15),
        ]);
    }
}
