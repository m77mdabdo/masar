{{--
    The error page for a site that is otherwise up: 403, 404, 419, 429.

    It uses the public layout, so the reader keeps the masthead and its
    navigation — the way back is the whole point of the page. 500 and 503 do
    not come here: see errors/standalone, which needs no database.
--}}
@php
    $locale = \App\Support\ErrorPageLocale::apply(request());

    // A URL that matches no route never runs the `web` group, so nothing has
    // shared $errors — and the newsletter band in the layout reads it. Laravel
    // hands the error view its own bag; sharing it is what keeps a 404 for
    // `/xx` from becoming a 500.
    view()->share('errors', $errors ?? new \Illuminate\Support\ViewErrorBag);

    $heading = __("errors.{$code}.heading");
    $body = __("errors.{$code}.body");
    $ui = __('errors.ui');
@endphp
<x-layout.public :title="$heading" :noindex="true">
    <x-ui.container class="py-14 sm:py-20">
        <div class="mx-auto max-w-[760px]">
            <header class="border-b border-line pb-6">
                <p class="font-display text-sm font-semibold text-gold-ink">{{ $ui['kicker'] }} <bdi>{{ $code }}</bdi></p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-g-950 sm:text-4xl">{{ $heading }}</h1>
                <p class="mt-4 text-lg leading-[1.95] text-ink">{{ $body }}</p>
            </header>

            @if ($code === 404)
                <form method="GET" action="{{ route('web.search', $locale) }}" class="mt-8 flex gap-2">
                    <label for="error-q" class="sr-only">{{ $ui['search_label'] }}</label>
                    <input
                        id="error-q" name="q" type="search"
                        placeholder="{{ $ui['search'] }}"
                        class="min-w-0 flex-1 rounded-lg border border-line bg-white px-4 py-3 focus:border-g-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-g-600"
                    />
                    <x-ui.button type="submit" size="lg">{{ $ui['search_button'] }}</x-ui.button>
                </form>

                @if ($sections->isNotEmpty())
                    <nav aria-labelledby="error-sections" class="mt-10">
                        <h2 id="error-sections" class="mb-3 font-display text-sm font-semibold text-ink-3">{{ $ui['sections'] }}</h2>
                        <ul class="grid gap-2 sm:grid-cols-2">
                            @foreach ($sections as $section)
                                <li>
                                    <a href="{{ route('web.category.show', [$locale, $section->slug]) }}"
                                       class="flex min-h-6 items-center justify-between rounded-lg border border-line bg-white px-4 py-3 font-medium text-g-900 hover:border-g-600">
                                        {{ $section->displayName() }}
                                        <x-ui.arrow />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            @endif

            <div class="mt-10">
                <x-ui.button :href="route('web.home', $locale)" variant="secondary">{{ $ui['home'] }}</x-ui.button>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
