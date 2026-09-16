<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Company;
use App\Queries\EntityProfileQuery;
use App\Queries\EntityContentQuery;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The payoff for the content graph: everything MASAR has published about a
 * company, across every content type, from one indexed query.
 */
class CompanyController
{
    public function __invoke(string $locale, string $slug): View
    {
        $company = Company::query()
            ->where('slug', $slug)
            ->with(['translations', 'industry.translations', 'country.translations'])
            ->first();

        if ($company === null) {
            throw new NotFoundHttpException;
        }

        // The aggregates a tag cannot produce. Assembled in a query object
        // rather than here — §3 keeps controllers thin and complex reads out
        // of them.
        $profile = EntityProfileQuery::for($company);

        return view('pages.company', [
            'company' => $company,
            'coverage' => $profile->coverage(),
            'timeline' => $profile->timeline(),
            'coMentions' => $profile->coMentions(),
            'mentions' => EntityContentQuery::for($company)->inLocale($locale)->paginate(15),
        ]);
    }
}
