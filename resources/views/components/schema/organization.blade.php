@php
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'NewsMediaOrganization',
        'name' => setting('identity.site_name'),
        'url' => route('web.home', config('masar.default_locale'), true),
        'logo' => setting('identity.logo_path'),
        'email' => setting('contact.editorial_email'),
        'sameAs' => array_values((array) setting('contact.social', [])) ?: null,
    ]);
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
