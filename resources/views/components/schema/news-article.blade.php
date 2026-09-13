@props(['article'])
@php
    $urls = app(App\Support\EntityUrl::class);
    $url = $urls->for($article, $article->locale, true);

    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $article->title,
        'description' => $article->meta_description ?: $article->subtitle,
        'inLanguage' => $article->locale,
        'datePublished' => $article->published_at?->toIso8601String(),
        'dateModified' => ($article->updated_content_at ?? $article->published_at)?->toIso8601String(),
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'image' => $article->heroMedia ? App\Support\MediaConversions::socialSrc($article->heroMedia) : null,
        'articleSection' => $article->category?->name,
        'author' => $article->author ? [
            '@type' => 'Person',
            'name' => $article->author->name,
            'url' => $urls->for($article->author, $article->locale, true),
        ] : null,
        'publisher' => [
            '@type' => 'NewsMediaOrganization',
            'name' => setting('identity.site_name'),
            'logo' => setting('identity.logo_path'),
        ],
        // Sponsored content is declared to search engines as well as to readers.
        'isAccessibleForFree' => true,
        'sponsor' => $article->is_sponsored && $article->sponsor_name
            ? ['@type' => 'Organization', 'name' => $article->sponsor_name]
            : null,
    ], fn ($value): bool => $value !== null && $value !== '');
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
