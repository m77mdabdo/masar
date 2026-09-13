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
    $direction = config("masar.locales.{$locale}.dir", 'rtl');
    $siteName = setting('identity.site_name');
    $pageTitle = $title
        ? str_replace(':title', $title, (string) setting('seo.title_template'))
        : $siteName;
    $ogImage = $image ?? setting('identity.og_image_path');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $pageTitle }}</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    @if ($noindex)<meta name="robots" content="noindex, follow">@endif

    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">

    {{-- hreflang for every enabled locale, plus x-default. Emitted on every page
         so an English version added later is discoverable the day it ships. --}}
    @foreach (config('masar.locales') as $code => $config)
        @continue (! ($config['enabled'] ?? false))
        <link rel="alternate" hreflang="{{ $code }}" href="{{ $alternates[$code] ?? route('web.home', $code) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('web.home', config('masar.default_locale')) }}">

    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $title ?? $siteName }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @if ($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
    <meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_SA' : 'en_US' }}">

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
    <link rel="preload" as="font" type="font/woff2" href="/fonts/readex-pro-600-arabic.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/fonts/ibm-plex-sans-arabic-400-arabic.woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('schema')
</head>
<body class="min-h-screen bg-cream text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-lg focus:bg-g-900 focus:px-4 focus:py-2 focus:text-cream">
        تخطَّ إلى المحتوى
    </a>

    <x-layout.topbar :locale="$locale" :links="$topbarLinks ?? []" />
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
