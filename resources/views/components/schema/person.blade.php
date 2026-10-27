@props(['author', 'topics' => null, 'url' => null, 'profilePage' => false])
@php
    $urls = app(App\Support\EntityUrl::class);
    $canonical = $url ?? $urls->for($author, app()->getLocale(), true);

    $person = array_filter([
        '@type' => 'Person',
        'name' => $author->name,
        'url' => $canonical,
        'jobTitle' => 'صحفي',
        'worksFor' => [
            '@type' => 'NewsMediaOrganization',
            'name' => setting('identity.site_name'),
            'url' => url('/'),
        ],
        // Only what our own records support. There is no bio field on a user
        // and no verified social profile, so no `description` and no `sameAs`:
        // an invented biography is a fabricated credential, which is the
        // fabrication rule (§5) pointed at the author rather than the story.
        'image' => $author->photo_path ?: null,
        'knowsAbout' => $topics?->pluck('name')->filter()->values()->all() ?: null,
    ]);

    /*
     * An author page is a ProfilePage whose mainEntity is the Person — the
     * shape Google documents for author and profile pages, and the one that
     * lets it attribute articles to a person rather than to a URL. Embedded
     * uses (an article's author) stay a bare Person, because there the page is
     * the article and not the profile.
     */
    $schema = $profilePage
        ? array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'mainEntity' => $person + ['mainEntityOfPage' => $canonical],
            'dateModified' => $author->updated_at?->toIso8601String(),
        ])
        : ['@context' => 'https://schema.org'] + $person;
@endphp
<script type="application/ld+json">{!! \App\Support\JsonLd::encode($schema) !!}</script>
