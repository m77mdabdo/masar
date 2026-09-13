@props(['author', 'topics' => null, 'url' => null])
@php
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => $author->name,
        'url' => $url ?? app(App\Support\EntityUrl::class)->for($author, app()->getLocale(), true),
        'jobTitle' => 'صحفي',
        'worksFor' => [
            '@type' => 'NewsMediaOrganization',
            'name' => setting('identity.site_name'),
        ],
        'knowsAbout' => $topics?->pluck('name')->filter()->values()->all() ?: null,
    ]);
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
