@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'canonical' => null,
    'noindex' => false,
    'ogType' => 'website',
    'alternates' => [],
    'ticker' => null,
    'topbarLinks' => null,
    'preload' => null,
    'preloadSizes' => null,
    'preloadType' => 'image/webp',
])
@php
    $locale = app()->getLocale();
    $direction = \App\Support\Locales::dir($locale);
    // An article passes its own (published translations only); every other
    // page is the same route in each enabled edition.
    $pageAlternates = $alternates ?: \App\Support\Alternates::forCurrentRoute();
    $siteName = setting('identity.site_name');
    // A page with no description of its own carries the edition's default.
    $description = $description ?: setting('seo.description');
    $pageTitle = $title
        ? str_replace(':title', $title, (string) setting('seo.title_template'))
        : $siteName;
    $ogImage = $image ?? setting('identity.og_image_path');
@endphp
<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag($locale) }}" dir="{{ $direction }}" data-script="{{ \App\Support\Locales::script($locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $pageTitle }}</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    {{-- Demo mode wins over the per-page flag and is stricter: nofollow as well,
         because we do not want a crawler walking from here into generated copy.
         The switch defaults closed, so a fresh deploy emits this. --}}
    @if (app(App\Support\DemoMode::class)->isOn())
        <meta name="robots" content="noindex, nofollow">
    @elseif ($noindex)
        <meta name="robots" content="noindex, follow">
    @endif

    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">

    {{-- hreflang for the editions this page really has, and only those
         (App\Support\Alternates). It used to list every enabled edition and
         point the missing ones at their front page, which told a crawler that
         an English home page was the English version of an Arabic story.
         x-default is the default edition's version, when there is one. --}}
    @foreach ($pageAlternates as $code => $href)
        <link rel="alternate" hreflang="{{ \App\Support\Locales::tag($code) }}" href="{{ $href }}">
    @endforeach
    @isset($pageAlternates[\App\Support\Locales::default()])
        <link rel="alternate" hreflang="x-default" href="{{ $pageAlternates[\App\Support\Locales::default()] }}">
    @endisset

    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $title ?? $siteName }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @if ($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
    <meta property="og:locale" content="{{ \App\Support\Locales::og($locale) }}">
    @foreach ($pageAlternates as $code => $href)
        @continue ($code === $locale)
        <meta property="og:locale:alternate" content="{{ \App\Support\Locales::og($code) }}">
    @endforeach

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? $siteName }}">
    @if ($description)<meta name="twitter:description" content="{{ $description }}">@endif
    @if ($ogImage)<meta name="twitter:image" content="{{ $ogImage }}">@endif

    <link rel="alternate" type="application/rss+xml" title="{{ $siteName }}" href="{{ route('web.rss.locale', $locale) }}">
    @if (setting('identity.favicon_path'))
        <link rel="icon" href="{{ setting('identity.favicon_path') }}">
    @endif

    {{--
        The LCP image, announced before the parser reaches it.

        Without this the browser finds the hero only after the stylesheet has
        arrived and the layout has been built — measured at roughly a third of
        LCP spent waiting to start the request. `imagesrcset` and `imagesizes`
        must match the <picture> exactly or the preload fetches a second file
        instead of the one the page uses.
    --}}
    @if ($preload)
        {{-- The type is declared so a browser without WebP skips the preload
             instead of fetching a file it will not use. --}}
        <link rel="preload" as="image" fetchpriority="high"
              type="{{ $preloadType }}"
              imagesrcset="{{ $preload }}"
              @if ($preloadSizes) imagesizes="{{ $preloadSizes }}" @endif>
    @endif

    {{-- Fonts are self-hosted. See masar-fonts.css for why the two faces use
         different font-display values. --}}
    {{-- Both faces are preloaded, and the body face especially because it is
         `optional`: a face that misses its 100ms window is dropped for the whole
         visit, and the preload is what gets it inside that window. Measured —
         removing it cost ~200ms of FCP and bought nothing back on LCP or CLS. --}}
    {{-- The subset preloaded is the one this edition's text is set in. Only
         Arabic uses the Arabic subset; every other edition sets its Latin in
         the brand faces and its own script in a system face, so preloading the
         Arabic file there would spend the window on glyphs no line uses. --}}
    @php($subset = \App\Support\Locales::script($locale) === 'arabic' ? 'arabic' : 'latin')
    <link rel="preload" as="font" type="font/woff2" href="/fonts/readex-pro-600-{{ $subset }}.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/fonts/ibm-plex-sans-arabic-400-{{ $subset }}.woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('schema')
</head>
<body class="min-h-screen bg-cream text-ink antialiased">
{{-- The demo disclosure lives in the topbar's first slot, not in a band
     of its own above the page: same words, every page, every width, no
     height of its own. --}}
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-lg focus:bg-g-900 focus:px-4 focus:py-2 focus:text-cream">
        {{ __('site.skip_to_content') }}
    </a>

    <x-layout.topbar :locale="$locale" :links="$topbarLinks ?? []" :alternates="$pageAlternates" />
    <x-layout.masthead :locale="$locale" :menu="$headerMenu ?? collect()" />
    {{-- Chrome, not content: the ticker sits above <main> with the masthead, so
         "skip to content" lands on the article and not on a row of figures. --}}
    @if ($ticker)
        <x-layout.ticker :figures="$ticker" :href="route('web.markets', $locale)" />
    @endif

    <main id="main">
        {{ $slot }}
    </main>

    <x-layout.newsletter-band :locale="$locale" />
    <x-layout.footer :locale="$locale" :explore="$footerExplore ?? collect()" :company="$footerCompany ?? collect()" />

    @stack('scripts')
</body>
</html>
