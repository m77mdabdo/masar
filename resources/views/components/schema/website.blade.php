@php
    $locale = app()->getLocale();
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => setting('identity.site_name'),
        'url' => route('web.home', $locale, true),
        'inLanguage' => $locale,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => route('web.search', $locale, true).'?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
@endphp
<script type="application/ld+json">{!! \App\Support\JsonLd::encode($schema) !!}</script>
