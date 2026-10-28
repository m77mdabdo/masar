@php $locale = app()->getLocale(); @endphp

<x-layout.public :title="__('pages.newsletter.unsubscribed')" :noindex="true">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-8 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">
                    {{ __('pages.newsletter.unsubscribed') }}
                </h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    {{ __('pages.newsletter.unsubscribed_body') }}
                </p>
            </header>

            <p class="text-sm text-ink-3">
                {{ __('pages.newsletter.changed_mind') }}
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <x-ui.button :href="route('web.newsletter', $locale)">{{ __('pages.newsletter.back') }}</x-ui.button>
                <x-ui.button variant="secondary" :href="route('web.home', $locale)">{{ __('site.nav.home') }}</x-ui.button>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
