@php
    $urls = app(App\Support\EntityUrl::class);
    $locale = app()->getLocale();
@endphp
<x-layout.public :title="$company->name" :description="$company->translate('short_description')">
    <x-ui.container class="py-10">
        <header class="mb-10 flex flex-wrap items-start gap-5 border-b border-line pb-6">
            @if ($company->logo_path)
                <img src="{{ $company->logo_path }}" alt="" width="64" height="64" class="h-16 w-16 rounded-lg object-contain" />
            @endif
            <div class="min-w-0 flex-1">
                <h1 class="font-display text-3xl font-semibold text-g-950">{{ $company->displayName() }}</h1>
                @if ($company->is_demo)
                    {{-- Demo companies are invented. A reader who arrives from a
                         shared link must not take one for a real business. --}}
                    <p class="mt-2"><x-ui.badge tone="gold">{{ __('pages.company.fictional') }}</x-ui.badge></p>
                @endif
                <div class="mt-2 flex flex-wrap gap-2 text-sm text-ink-3">
                    <x-ui.tag>{{ $company->type?->label() }}</x-ui.tag>
                    @if ($company->industry?->name)<x-ui.tag>{{ $company->industry->displayName() }}</x-ui.tag>@endif
                    @if ($company->country?->name)<x-ui.tag>{{ $company->country->displayName() }}</x-ui.tag>@endif
                    @if ($company->ticker)<span class="ltr-isolate nums-tabular">{{ $company->ticker }}</span>@endif
                </div>
                @if ($company->translate('description'))
                    <p class="mt-3 max-w-2xl leading-relaxed text-ink-3">{{ $company->translate('description') }}</p>
                @endif
            </div>
        </header>

        {{-- Key facts. A definition list, not a row of tags: these are the
             things a reader checks before quoting a company, and they should be
             readable as pairs rather than scanned as decoration. --}}
        @php
            $facts = collect([
                __('pages.opportunities.sector') => $company->industry?->name,
                __('pages.company.hq') => $company->country?->name,
                __('pages.company.founded') => $company->founded_year,
                __('pages.company.ticker') => $company->ticker,
            ])->filter();
        @endphp

        @if ($facts->isNotEmpty() || $company->website)
            <section class="mb-10 rounded-xl border border-line bg-white p-5" aria-labelledby="company-facts">
                <h2 id="company-facts" class="sr-only">{{ __('pages.company.facts') }}</h2>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($facts as $label => $value)
                        <div>
                            <dt class="text-[0.7rem] uppercase tracking-[0.08em] text-ink-3">{{ $label }}</dt>
                            <dd class="mt-1 font-display text-base text-g-950"><bdi class="nums-tabular">{{ $value }}</bdi></dd>
                        </div>
                    @endforeach
                </dl>

                @php $companyUrl = \App\Support\SafeUrl::external($company->website); @endphp
                @if ($companyUrl)
                    <a href="{{ $companyUrl }}" rel="nofollow noopener" target="_blank"
                       class="mt-4 inline-block text-sm text-gold-ink underline">{{ __('pages.company.website') }}</a>
                @endif
            </section>
        @endif

        {{-- What our coverage of this company actually consists of. A list of
             articles is something any CMS can produce; the shape of the
             coverage is what the graph knows. --}}
        @if ($coverage->isNotEmpty())
            <section class="mb-10" aria-labelledby="company-coverage">
                <x-ui.section-rule :title="__('pages.company.coverage_shape')" id="company-coverage" />
                <div class="flex flex-wrap gap-2">
                    @foreach ($coverage as $row)
                        <span class="inline-flex items-baseline gap-2 rounded-full border border-line bg-white px-3 py-1.5 text-sm">
                            <span class="text-ink"><x-ui.bidi :text="$row['label']" /></span>
                            <span class="nums-tabular font-display font-semibold text-g-700">{{ $row['count'] }}</span>
                        </span>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- How long we have been following it. Bars are proportional to the
             busiest year, and every figure is a count we hold — nothing here is
             an estimate. --}}
        @if ($timeline->count() > 1)
            @php $peak = max($timeline->pluck('count')->all()); @endphp
            <section class="mb-10" aria-labelledby="company-timeline">
                <x-ui.section-rule :title="__('pages.company.timeline')" id="company-timeline" />
                <ul class="space-y-2">
                    @foreach ($timeline as $row)
                        <li class="flex items-center gap-3">
                            <span class="nums-tabular w-12 shrink-0 text-sm text-ink-3">{{ $row['year'] }}</span>
                            <span class="h-2 rounded-full bg-g-700" style="width: {{ max(4, (int) round($row['count'] / $peak * 100)) }}%"></span>
                            <span class="nums-tabular text-sm text-ink-3">{{ $row['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- The query a tag cannot answer: who else appears in the stories where
             this company appears. This is why entity_mentions is polymorphic. --}}
        @if ($coMentions->isNotEmpty())
            <section class="mb-10" aria-labelledby="company-co-mentions">
                <x-ui.section-rule :title="__('pages.company.co_mentions')" id="company-co-mentions" />
                <div class="grid gap-6 sm:grid-cols-2">
                    @foreach ($coMentions as $type => $rows)
                        <div>
                            <h3 class="mb-2 text-[0.7rem] uppercase tracking-[0.08em] text-ink-3">
                                {{ $type === 'person' ? __('site.ui.people') : ($type === 'company' ? __('site.ui.companies') : __('site.ui.topics')) }}
                            </h3>
                            <ul class="space-y-1.5">
                                @foreach ($rows as $row)
                                    <li class="flex items-baseline justify-between gap-3">
                                        <a class="truncate text-sm text-g-900 underline-offset-2 hover:underline"
                                           href="{{ $type === 'person'
                                               ? route('web.person.show', [$locale, $row['slug']])
                                               : ($type === 'company'
                                                   ? route('web.company.show', [$locale, $row['slug']])
                                                   : route('web.topic.show', [$locale, $row['slug']])) }}"><x-ui.bidi :text="$row['name']" /></a>
                                        <span class="nums-tabular shrink-0 text-xs text-ink-3">{{ trans_choice('site.count.stories', $row['count']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- The content graph's payoff: every content type that mentions this
             company, newest first, from one indexed query. --}}
        <x-ui.section-rule :title="__('pages.company.all')" />

        <div class="space-y-6">
            @forelse ($mentions as $mention)
                @php $item = $mention->mentionable; @endphp
                @continue (! $item)
                @if ($item instanceof App\Models\Article)
                    <x-article.card-row :article="$item" />
                @else
                    <x-entity.opportunity-card :opportunity="$item" />
                @endif
            @empty
                <p class="py-16 text-center text-ink-3">{{ __('pages.company.empty') }}</p>
            @endforelse
        </div>

        @if ($mentions->hasPages())
            <div class="mt-10">{{ $mentions->links() }}</div>
        @endif
    </x-ui.container>
</x-layout.public>
