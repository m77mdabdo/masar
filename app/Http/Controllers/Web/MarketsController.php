<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ContentType;
use App\Models\Market;
use App\Queries\PublishedArticlesQuery;
use Illuminate\Contracts\View\View;

/**
 * "Reading the market", not live data.
 *
 * No ticker licence and no real-time feed, per the standing ruling. Index cards
 * render only figures an editor has entered against a market, with the date they
 * were entered — a number without an "as of" is a number nobody can trust, and a
 * number we invented is worse than none.
 */
class MarketsController
{
    public function __invoke(string $locale): View
    {
        $lead = PublishedArticlesQuery::make()
            ->forLocale($locale)
            ->builder()
            ->where('content_type', ContentType::Analysis->value)
            ->latest('published_at')
            ->first();

        return view('pages.markets', [
            'lead' => $lead,
            'markets' => Market::query()->with('translations')->orderBy('slug')->get(),
            'articles' => PublishedArticlesQuery::make()
                ->forLocale($locale)
                ->excluding($lead?->getKey() ?? [])
                ->latest()
                ->paginate((int) setting('editorial.articles_per_page')),
        ]);
    }
}
