@php $locale = app()->getLocale(); @endphp

{{--
    This page describes an editorial commitment, not a mechanism.

    It used to describe one: a correction line appended to the article with the
    old wording, the new wording and the date, and a prominent placement for
    material errors. None of that exists in the schema — there is no corrections
    table, no correction field on articles, and nothing renders one. A reader
    had no way to know that, which is precisely what made it a false claim
    rather than an aspiration.

    Every sentence here is now in the first person plural and describes what we
    do. When the corrections feature is built (DEFERRED.md), this page can go
    back to describing the mechanism — and the sentence that describes it will
    owe a test.
--}}
<x-layout.public :title="__('pages.corrections.title')" :description="__('pages.corrections.description')">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">{{ __('pages.corrections.title') }}</h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    {!! __('pages.corrections.lede', ['link' => '<a href="'.e(route('web.editorial-standards', $locale)).'" class="text-gold-ink underline">'.e(__('pages.corrections.standards_link')).'</a>']) !!}
                </p>
            </header>

            <div class="prose-masar">
                <h2>{{ __('pages.corrections.what') }}</h2>
                <p>
                    {{ __('pages.corrections.what_body') }}
                </p>

                <h2>{{ __('pages.corrections.how') }}</h2>
                <p>
                    {{ __('pages.corrections.how_body') }}
                </p>
                <p class="text-sm text-ink-3">
                    {{ __('pages.corrections.commitment') }}
                </p>

                <h2>{{ __('pages.corrections.when') }}</h2>
                <p>
                    {{ __('pages.corrections.when_body') }}
                </p>

                <h2>{{ __('pages.corrections.report') }}</h2>
                <p>
                    {!! __('pages.corrections.report_body', ['link' => '<a href="'.e(route('web.contact', $locale)).'" class="text-gold-ink underline">'.e(__('pages.advertise.contact_link')).'</a>']) !!}
                </p>

                <h2>{{ __('pages.corrections.not') }}</h2>
                <p>
                    {{ __('pages.corrections.not_body') }}
                </p>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
