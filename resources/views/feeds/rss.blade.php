<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ setting('identity.site_name') }}</title>
        <link>{{ route('web.home', $feedLocale, true) }}</link>
        <description>{{ setting('identity.tagline') }}</description>
        <language>{{ $feedLocale }}</language>
        <atom:link href="{{ route('web.rss.locale', $feedLocale, true) }}" rel="self" type="application/rss+xml" />
@foreach ($articles as $article)
        @php $link = $urls->for($article, $article->locale, true); @endphp
        @continue (! $link)
        <item>
            <title>{{ $article->title }}</title>
            <link>{{ $link }}</link>
            <guid isPermaLink="true">{{ $link }}</guid>
            <pubDate>{{ $article->published_at?->toRfc2822String() }}</pubDate>
            @if ($article->category?->name)<category>{{ $article->category->name }}</category>@endif
            <description><![CDATA[{{ $article->subtitle ?: Str::limit(strip_tags((string) $article->body), 300) }}]]></description>
        </item>
@endforeach
    </channel>
</rss>
