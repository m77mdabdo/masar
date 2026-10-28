<x-layout.public :title="__('pages.markets.title')" :description="__('pages.markets.description')">
    <x-ui.container class="py-10">
        <header class="mb-10 border-b border-line pb-6">
            <h1 class="font-display text-3xl font-semibold text-g-950 sm:text-4xl">{{ __('pages.markets.title') }}</h1>
            <p class="mt-2 max-w-2xl text-ink-3">
                {{ __('pages.markets.lede') }}
            </p>
        </header>

        @if ($lead)
            <section class="mb-12">
                <x-article.card-overlay :article="$lead" size="lg" :level="2" :eager="true" />
            </section>
        @endif

        @if ($markets->isNotEmpty())
            <section class="mb-12">
                <x-ui.section-rule :title="__('pages.markets.tracked')" />

                <x-ui.grid :cols="3" gap="sm">
                    @foreach ($markets as $market)
                        <div class="rounded-xl border border-line bg-white p-4">
                            <div class="font-display text-base font-medium text-g-950">{{ $market->displayName() }}</div>

                            @if ($market->code)
                                <div class="ltr-isolate nums-tabular mt-0.5 text-xs text-ink-3">{{ $market->code }}</div>
                            @endif

                            {{-- No figure is rendered unless an editor recorded one.
                                 An empty slot is honest; an invented number is not. --}}
                            <p class="mt-3 text-sm text-ink-3">
                                {{ __('pages.markets.editorial_only') }}
                            </p>
                        </div>
                    @endforeach
                </x-ui.grid>
            </section>
        @endif

        <section>
            <x-ui.section-rule :title="__('pages.markets.latest')" />

            <div class="space-y-6">
                @forelse ($articles as $article)
                    <x-article.card-row :article="$article" />
                @empty
                    <p class="py-16 text-center text-ink-3">{{ __('pages.markets.empty') }}</p>
                @endforelse
            </div>

            <div class="mt-10">{{ $articles->links() }}</div>
        </section>
    </x-ui.container>
</x-layout.public>
