@php $locale = app()->getLocale(); @endphp

<x-layout.public :title="__('pages.advertise.title')" :description="__('pages.advertise.description')">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">{{ __('pages.advertise.title') }}</h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    {{ __('pages.advertise.lede') }}
                </p>
            </header>

            <div class="prose-masar">
                <h2>{{ __('pages.advertise.offer') }}</h2>
                <p>
                    {{ __('pages.advertise.offer_body') }}
                </p>

                <h2>{{ __('pages.advertise.separation') }}</h2>
                <p>
                    {{ __('pages.advertise.separation_body') }}
                </p>
                <p>
                    {{ __('pages.advertise.enforced') }}
                </p>

                <h2>{{ __('pages.advertise.disclosure') }}</h2>
                <p>
                    {{ __('pages.advertise.disclosure_body', ['label' => __('article.sponsored')]) }}
                </p>

                <h2>{{ __('pages.advertise.refuse') }}</h2>
                <ul>
                    <li>{{ __('pages.advertise.refuse_1') }}</li>
                    <li>{{ __('pages.advertise.refuse_2') }}</li>
                    <li>{{ __('pages.advertise.refuse_3') }}</li>
                </ul>

                <h2>{{ __('pages.advertise.contact') }}</h2>
                <p>
                    {!! __('pages.advertise.contact_body', ['link' => '<a href="'.e(route('web.contact', $locale)).'" class="text-gold-ink underline">'.e(__('pages.advertise.contact_link')).'</a>']) !!}
                </p>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
