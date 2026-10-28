<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($entries as $entry)
    <url>
        <loc>{{ $entry['loc'] }}</loc>
        @if ($entry['lastmod'])<lastmod>{{ $entry['lastmod'] }}</lastmod>@endif
        {{-- A page lists every edition of itself, itself included, or none:
             Google ignores a hreflang set that is not reciprocal. --}}
        @if (count($entry['alternates']) > 1)
            @foreach ($entry['alternates'] as $code => $href)
        <xhtml:link rel="alternate" hreflang="{{ \App\Support\Locales::tag($code) }}" href="{{ $href }}"/>
            @endforeach
        @endif
    </url>
@endforeach
</urlset>
