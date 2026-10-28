@php
    $locale = app()->getLocale();
    $current = request()->only(['sector', 'region', 'potential', 'deadline', 'open_only']);
@endphp

<x-layout.public :title="__('pages.opportunities.title')" :description="__('pages.opportunities.description')">
    <x-ui.container class="py-10">
        <header class="mb-8 border-b border-line pb-6">
            <h1 class="font-display text-3xl font-semibold text-g-950 sm:text-4xl">{{ __('pages.opportunities.title') }}</h1>
            <p class="mt-2 text-ink-3">{{ __('pages.opportunities.lede') }}</p>
        </header>

        {{-- Filters are GET parameters applied in SQL. The list is paginated, so
             filtering in PHP would page over the wrong set.

             The action is named even though GET-to-self would work, because
             "every form states where it goes" is the rule that lets the decay
             check find a form that goes nowhere without reading each one. --}}
        <form method="GET" action="{{ route('web.opportunities.index', $locale) }}" class="mb-8 space-y-4 rounded-xl border border-line bg-white p-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="sector" class="mb-1 block text-xs text-ink-3">{{ __('pages.opportunities.sector') }}</label>
                    <select id="sector" name="sector" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm">
                        <option value="">{{ __('site.ui.all') }}</option>
                        @foreach ($sectors as $sector)
                            <option value="{{ $sector->slug }}" @selected(request('sector') === $sector->slug)>{{ $sector->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="region" class="mb-1 block text-xs text-ink-3">{{ __('pages.opportunities.scope') }}</label>
                    <select id="region" name="region" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm">
                        <option value="">{{ __('site.ui.all') }}</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->slug }}" @selected(request('region') === $region->slug)>{{ $region->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="potential" class="mb-1 block text-xs text-ink-3">{{ __('pages.opportunities.potential') }}</label>
                    <select id="potential" name="potential" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm">
                        <option value="">{{ __('site.ui.all') }}</option>
                        @foreach ($potentials as $potential)
                            <option value="{{ $potential->value }}" @selected(request('potential') === $potential->value)>{{ $potential->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="deadline" class="mb-1 block text-xs text-ink-3">{{ __('pages.opportunities.closes_within') }}</label>
                    <select id="deadline" name="deadline" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm">
                        <option value="">{{ __('pages.opportunities.any_time') }}</option>
                        <option value="7" @selected(request('deadline') === '7')>{{ trans_choice('pages.opportunities.days', 7, ['days' => 7]) }}</option>
                        <option value="30" @selected(request('deadline') === '30')>{{ trans_choice('pages.opportunities.days', 30, ['days' => 30]) }}</option>
                        <option value="90" @selected(request('deadline') === '90')>{{ trans_choice('pages.opportunities.days', 90, ['days' => 90]) }}</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="open_only" value="1" @checked(request()->boolean('open_only'))
                           class="rounded border-line text-g-900 focus:ring-g-600" />
                    {{ __('pages.opportunities.open_only') }}
                </label>

                <div class="flex gap-2">
                    @if (array_filter($current))
                        <x-ui.button :href="route('web.opportunities.index', $locale)" variant="ghost" size="sm">{{ __('pages.opportunities.clear') }}</x-ui.button>
                    @endif
                    <x-ui.button type="submit" size="sm">{{ __('pages.opportunities.filter') }}</x-ui.button>
                </div>
            </div>
        </form>

        <h2 class="sr-only">{{ __('pages.opportunities.results') }}</h2>
        <p class="nums-tabular mb-6 text-sm text-ink-3">{{ trans_choice('site.count.opportunities', $opportunities->total()) }}</p>

        <x-ui.grid :cols="3">
            @forelse ($opportunities as $opportunity)
                <x-entity.opportunity-card :opportunity="$opportunity" />
            @empty
                <div class="col-span-full rounded-xl border border-line bg-white p-8 text-center">
                    <p class="font-display text-lg text-g-900">{{ __('pages.opportunities.none') }}</p>
                    <p class="mt-2 text-ink-3">{{ __('pages.opportunities.widen') }}</p>
                    <x-ui.button :href="route('web.opportunities.index', $locale)" variant="secondary" size="sm" class="mt-4">
                        {{ __('pages.opportunities.all') }}
                    </x-ui.button>
                </div>
            @endforelse
        </x-ui.grid>

        <div class="mt-10">{{ $opportunities->links() }}</div>
    </x-ui.container>
</x-layout.public>
