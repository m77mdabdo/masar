@props(['items' => []])
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($items)->values()->map(fn (array $item, int $i): array => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $item['name'],
            'item' => $item['url'],
        ])->all(),
    ];
@endphp
<script type="application/ld+json">{!! \App\Support\JsonLd::encode($schema) !!}</script>
