@php $locale = app()->getLocale(); @endphp

<x-layout.public :title="__('pages.about.title')" :description="__('pages.about.description')">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">{{ __('pages.about.title') }}</h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">{{ setting('identity.tagline') }}</p>
            </header>

            <div class="prose-masar">
                <p>
                    {{ __('pages.about.intro') }}
                </p>

                <h2>{{ __('pages.about.difference') }}</h2>
                <p>
                    {{ __('pages.about.difference_body') }}
                </p>

                <h2>{{ __('pages.about.audience') }}</h2>
                <p>
                    {{ __('pages.about.audience_body') }}
                </p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <a href="{{ route('web.editorial-standards', $locale) }}"
                   class="rounded-xl border border-line bg-white p-5 transition hover:border-g-600">
                    <h2 class="font-display text-lg font-semibold text-g-900">{{ __('site.footer.editorial_standards') }}</h2>
                    <p class="mt-1 text-sm text-ink-3">{{ __('pages.about.standards_teaser') }}</p>
                </a>
                <a href="{{ route('web.contact', $locale) }}"
                   class="rounded-xl border border-line bg-white p-5 transition hover:border-g-600">
                    <h2 class="font-display text-lg font-semibold text-g-900">{{ __('pages.contact.title') }}</h2>
                    <p class="mt-1 text-sm text-ink-3">{{ __('pages.about.contact_teaser') }}</p>
                </a>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
