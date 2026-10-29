{{--
    The error page for a site that is not up: 500 and 503.

    It must render when the thing that failed is the database, the cache or a
    setting, so it reads none of them — no public layout, no composer, no
    setting(), no DemoMode. Only the compiled stylesheet and the self-hosted
    fonts, which are files. The brand lockup is written out for the same reason
    the masthead's is read from settings: here there may be no settings to read.

    It claims nothing about what happens next. "We have been notified" would be
    a sentence about a system this page cannot see (CLAUDE.md §5).
--}}
@php
    $locale = \App\Support\ErrorPageLocale::apply(request());
    $direction = \App\Support\ErrorPageLocale::direction($locale);

    // lang/ files are files: reading them needs no database, cache or setting.
    $heading = __("errors.{$code}.heading");
    $body = __("errors.{$code}.body");
    $retry = __('errors.ui.retry');
@endphp
<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag($locale) }}" dir="{{ $direction }}" data-script="{{ \App\Support\Locales::script($locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $heading }} — MASAR</title>
    <link rel="preload" as="font" type="font/woff2" href="/fonts/readex-pro-600-arabic.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/fonts/ibm-plex-sans-arabic-400-arabic.woff2" crossorigin>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col bg-cream text-ink antialiased">
    <header class="border-b border-line">
        <x-ui.container>
            <div class="flex h-[3.375rem] items-center">
                <span class="flex items-baseline gap-2.5">
                    <span class="font-display text-[2.375rem] font-semibold leading-none tracking-[0.05em] text-g-950">MASAR</span>
                    <span class="font-display text-[1.5rem] font-bold leading-none text-g-700">مسار</span>
                </span>
            </div>
        </x-ui.container>
    </header>

    <main id="main" class="flex-1">
        <x-ui.container class="py-14 sm:py-20">
            <div class="mx-auto max-w-[760px]">
                <p class="font-display text-sm font-semibold text-gold-ink"><bdi>{{ $code }}</bdi></p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-g-950 sm:text-4xl">{{ $heading }}</h1>
                <p class="mt-4 text-lg leading-[1.95] text-ink">{{ $body }}</p>
                <div class="mt-10">
                    <x-ui.button :href="url()->current()" variant="secondary">{{ $retry }}</x-ui.button>
                </div>
            </div>
        </x-ui.container>
    </main>
</body>
</html>
