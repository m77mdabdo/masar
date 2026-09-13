<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\OpportunityPotential;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Opportunity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OpportunityController
{
    /**
     * Filters are applied in SQL, not in memory — the list is paginated and a
     * PHP filter would page over the wrong set.
     */
    public function index(Request $request, string $locale): View
    {
        $query = Opportunity::query()
            ->published()
            ->forLocale($locale)
            ->with(['industry.translations', 'country.translations'])
            ->when($request->string('sector')->isNotEmpty(), fn ($q) => $q->whereHas(
                'industry',
                fn ($i) => $i->where('slug', $request->string('sector')),
            ))
            ->when($request->string('region')->isNotEmpty(), fn ($q) => $q->whereHas(
                'country',
                fn ($c) => $c->where('slug', $request->string('region')),
            ))
            ->when($request->string('potential')->isNotEmpty(), fn ($q) => $q->where(
                'potential',
                $request->string('potential'),
            ))
            ->when($request->boolean('open_only'), fn ($q) => $q->open())
            ->when($request->string('deadline')->isNotEmpty(), fn ($q) => $q->whereNotNull('deadline')
                ->whereBetween('deadline', [now(), now()->addDays((int) $request->string('deadline')->toString())]));

        return view('pages.opportunities', [
            'opportunities' => $query
                ->orderByDesc('published_at')
                ->paginate(12)
                ->withQueryString(),
            'potentials' => OpportunityPotential::cases(),
            // Filter options come from what actually exists, so an option never
            // leads to an empty page.
            'sectors' => Industry::query()
                ->whereHas('opportunities', fn ($q) => $q->published()->forLocale($locale))
                ->with('translations')->orderBy('slug')->get(),
            'regions' => Country::query()
                ->whereHas('opportunities', fn ($q) => $q->published()->forLocale($locale))
                ->with('translations')->orderBy('slug')->get(),
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $opportunity = Opportunity::query()
            ->published()
            ->forLocale($locale)
            ->where('slug', $slug)
            ->with(['industry.translations', 'country.translations'])
            ->first();

        if ($opportunity === null) {
            throw new NotFoundHttpException;
        }

        return view('pages.opportunity', ['opportunity' => $opportunity]);
    }
}
