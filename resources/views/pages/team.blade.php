@php $locale = app()->getLocale(); @endphp

{{-- This page names nobody until there is somebody real to name. Listing the
     demo people here would invent a masthead of staff for a real brand, which
     is the fabrication rule in CLAUDE.md §5 applied to people rather than to
     quotes. `people.is_expert` is never seeded true, so in demo the list below
     is empty and the page says why. --}}
<x-layout.public :title="__('pages.team.title')" :description="__('pages.team.description')">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">{{ __('pages.team.title') }}</h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    {{ __('pages.team.lede') }}
                </p>
            </header>

            @if ($people->isNotEmpty())
                <section class="mb-12">
                    <x-ui.section-rule :title="__('pages.team.team')" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($people as $person)
                            <a href="{{ route('web.person.show', [$locale, $person->slug]) }}"
                               class="rounded-xl border border-line bg-white p-4 transition hover:border-g-600">
                                <div class="font-display text-base font-medium text-g-950">{{ $person->name }}</div>
                                @if ($person->translate('role'))
                                    <div class="mt-1 text-xs text-ink-3">{{ $person->translate('role') }}</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @else
                <div class="mb-12 rounded-xl border border-line bg-white p-6">
                    <p class="text-ink-3">
                        {{ __('pages.team.no_names') }}
                    </p>
                </div>
            @endif

            <div class="prose-masar">
                <h2>{{ __('pages.team.how') }}</h2>
                <p>
                    {{ __('pages.team.how_body') }}
                </p>

                <h2>{{ __('pages.team.blocks') }}</h2>
                <p>
                    {{ __('pages.team.blocks_body') }}
                </p>

                <h2>{{ __('pages.team.accountability') }}</h2>
                <p>
                    {{-- The link is built here and dropped into the sentence, so each
                         language puts it where its grammar wants it. --}}
                    {!! __('pages.team.accountability_body', ['policy' => '<a href="'.e(route('web.corrections', $locale)).'" class="text-gold-ink underline">'.e(__('pages.team.corrections_link')).'</a>']) !!}
                </p>

                <h2>{{ __('pages.team.experts') }}</h2>
                <p>
                    {{ __('pages.team.experts_body') }}
                </p>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
