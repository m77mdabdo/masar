@php $locale = app()->getLocale(); @endphp

<x-layout.public
    :title="__('site.footer.editorial_standards')"
    :description="__('pages.standards.description')"
>
    @push('schema')
        <x-schema.breadcrumbs :items="[
            ['name' => setting('identity.site_name'), 'url' => route('web.home', $locale, true)],
            ['name' => __('site.footer.editorial_standards'), 'url' => route('web.editorial-standards', $locale, true)],
        ]" />
    @endpush

    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">{{ __('site.footer.editorial_standards') }}</h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    {{ __('pages.standards.lede') }}
                </p>
            </header>

            <div class="prose-masar">
                <h2>{{ __('pages.standards.publish') }}</h2>
                <p>
                    {{ __('pages.standards.publish_body') }}
                </p>

                <h2>{{ __('pages.standards.four') }}</h2>
                <p>
                    {{ __('pages.standards.four_body') }}
                </p>

                <h2>{{ __('pages.standards.verify') }}</h2>
                <p>
                    {{ __('pages.standards.verify_body') }}
                </p>

                <h2>{{ __('pages.standards.sources') }}</h2>
                <p>
                    {{ __('pages.standards.sources_body') }}
                </p>
                <p>
                    {{ __('pages.standards.commitment') }}
                </p>

                <h2>{{ __('pages.standards.paid') }}</h2>
                <p>
                    {{ __('pages.standards.paid_body') }}
                </p>
                <p>
                    {{ __('pages.standards.paid_2') }}
                </p>

                <h2>{{ __('pages.standards.corrections') }}</h2>
                <p>
                    {{ __('pages.standards.corrections_body') }}
                </p>

                <h2>{{ __('pages.standards.independence') }}</h2>
                <p>
                    {{ __('pages.standards.independence_body') }}
                </p>

                <h2>{{ __('pages.standards.ai') }}</h2>
                <p>
                    {{ __('pages.standards.ai_body') }}
                </p>

                <h2>{{ __('pages.standards.translation') }}</h2>
                <p>{{ __('pages.standards.translation_body') }}</p>
                {{-- Read from the review register at request time, so the page
                     cannot say a review happened that the register does not
                     record (R11). Arabic is the source and says nothing. --}}
                @unless ($locale === \App\Support\Locales::default())
                    @php
                        $reviews = \App\Models\TranslationReview::query()->where('locale', $locale)->pluck('status');
                        // "Reviewed" only when the interface is approved AND no story
                        // in this edition is still a machine-assisted translation.
                        $reviewed = $reviews->isNotEmpty() && $reviews->every(fn ($s) => $s === \App\Enums\TranslationReviewStatus::Approved)
                            && ! \App\Models\Article::query()->published()->where('locale', $locale)
                                ->where('translation_status', \App\Enums\TranslationStatus::OwnerAuthorized)->exists();
                    @endphp
                    <p>{{ $reviewed ? __('pages.standards.interface_reviewed') : __('pages.standards.interface_pending') }}</p>
                @endunless
            </div>

            <aside class="mt-12 rounded-xl border border-line bg-white p-6">
                <h2 class="font-display text-lg font-semibold text-g-900">{{ __('pages.standards.found_error') }}</h2>
                <p class="mt-2 text-ink-3">
                    {{ __('pages.standards.found_error_body') }}
                </p>
                @if (setting('contact.editorial_email'))
                    <x-ui.button :href="'mailto:' . setting('contact.editorial_email')" class="mt-4">
                        {{ __('pages.standards.write_newsroom') }}
                    </x-ui.button>
                @endif
            </aside>
        </div>
    </x-ui.container>
</x-layout.public>
