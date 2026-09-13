<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Person;
use App\Queries\EntityContentQuery;
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

        return view('pages.person', [
            'person' => $person,
            'mentions' => EntityContentQuery::for($person)->inLocale($locale)->paginate(15),
        ]);
    }
}
