<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
{{-- Google News reads the last 48 hours only; older entries dilute the feed. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
@foreach ($articles as $article)
    @php $loc = $urls->for($article, $article->locale, true); @endphp
    @continue (! $loc)
    <url>
        <loc>{{ $loc }}</loc>
        <news:news>
            <news:publication>
                <news:name>{{ setting('identity.site_name') }}</news:name>
                <news:language>{{ $article->locale }}</news:language>
            </news:publication>
            <news:publication_date>{{ $article->published_at?->toAtomString() }}</news:publication_date>
            <news:title>{{ $article->title }}</news:title>
        </news:news>
    </url>
@endforeach
</urlset>
