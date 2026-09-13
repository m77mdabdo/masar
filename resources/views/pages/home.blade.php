@php
    $locale = app()->getLocale();

    // Sections arrive from ComposeHomepage in the editor's order. Two runs are
    // laid out as one band rather than stacked — success stories beside most
    // read and editor's picks, and podcast / reports / data — so they are pulled
    // out of the flow here by type, keeping the editor's order inside each row.
    $byType = $sections->keyBy('type');
    $railRows = [
        ['stories', 'most_read', 'editors_picks'],
        ['podcast', 'reports', 'data'],
    ];
    $grouped = collect($railRows)->flatten()->flip();
    $has = fn (string $type): bool => ($byType[$type]['items'] ?? collect())->isNotEmpty();

    $bigStory = collect($byType['big_story']['items'] ?? [])->first();
    $lcpSizes = '(max-width: 1024px) 100vw, 62vw';
@endphp

<x-layout.public
    :title="setting('identity.site_name')"
    :description="setting('identity.tagline')"
    :ticker="$figures"
    :topbarLinks="$topbarLinks"
    :preload="$bigStory?->heroMedia ? App\Support\MediaConversions::srcset($bigStory->heroMedia) : null"
    :preloadSizes="$lcpSizes"
>
    @push('schema')
        <x-schema.website />
        <x-schema.organization />
    @endpush

    <x-ui.container class="pb-14">
        {{-- The big story carries the document's h1. Where there is none — a
             brand-new installation — the page still needs one, so the hidden
             fallback appears only then. --}}
        @unless ($bigStory)
            <h1 class="sr-only">{{ setting('identity.site_name') }} — {{ setting('identity.tagline') }}</h1>
        @endunless

        @forelse ($sections as $section)
            @php
                $type = $section['type'];
                $items = $section['items'];
                $deferPaint = ! in_array($type, ['big_story', 'leads'], true);
            @endphp

            {{-- A section with nothing in it renders nothing: an empty rail with
                 a heading looks broken to a reader and tells them nothing. --}}
            @continue ($items->isEmpty() && ! in_array($type, ['newsletter', 'tiles', 'data'], true))

            <div @class(['defer-paint' => $deferPaint])>
            @if ($grouped->has($type))
                {{-- Rail members are rendered by the row that owns them, at the
                     position of the first one. --}}
                @foreach ($railRows as $row)
                    @continue ($row[0] !== $type)

                    @if ($row[0] === 'stories')
                        <x-home.rail-row :cols="2">
                            @if ($has('stories'))
                                <x-home.rail :section="$byType['stories']" weight="story" />
                            @endif
                            <div class="space-y-9">
                                @if ($has('most_read'))
                                    <x-home.rail :section="$byType['most_read']" :ranked="true" />
                                @endif
                                @if ($has('editors_picks'))
                                    <x-home.rail :section="$byType['editors_picks']" marker="◆" />
                                @endif
                            </div>
                        </x-home.rail-row>
                    @else
                        <x-home.rail-row :cols="3">
                            @if ($has('podcast'))<x-home.podcast :section="$byType['podcast']" />@endif
                            @if ($has('reports'))<x-home.reports :section="$byType['reports']" />@endif
                            <x-home.data-grid
                                :section="$byType['data'] ?? ['type' => 'data', 'title' => 'بيانات', 'items' => collect()]"
                                :figures="$figures"
                                :href="route('web.markets', $locale)"
                            />
                        </x-home.rail-row>
                    @endif
                @endforeach

            @elseif ($type === 'big_story')
                <x-home.big-story :section="$section" :sizes="$lcpSizes" />

            @elseif ($type === 'leads')
                <x-home.leads :section="$section" />

            @elseif ($type === 'tiles')
                <x-home.tiles :section="$section" :categories="$categoryTiles" :href="route('web.home', $locale)" />

            @elseif ($type === 'saudi')
                <x-home.feature-rows
                    :section="$section"
                    :href="route('web.category.show', [$locale, $section['config']['category_slug'] ?? 'saudi'])"
                />

            @elseif ($type === 'markets')
                <x-home.markets :section="$section" :figures="$figures" :href="route('web.markets', $locale)" />

            @elseif ($type === 'business')
                <x-home.cards
                    :section="$section" :cols="3"
                    :href="route('web.category.show', [$locale, $section['config']['category_slug'] ?? 'business'])"
                />

            @elseif ($type === 'opportunities')
                <x-home.opportunities :section="$section" :mediaPool="$opportunityMedia" :href="route('web.opportunities.index', $locale)" />

            @elseif ($type === 'intelligence')
                <x-home.intelligence
                    :section="$section" :figures="$figures"
                    :opportunity="$opportunityOfTheWeek"
                    :media="$opportunityMedia->last()"
                    :href="route('web.opportunities.index', $locale)"
                />

            @elseif ($type === 'insights')
                {{-- No rule above this one. Deliberate, and it is in the files. --}}
                <x-home.cards :section="$section" :cols="4" :rule="false" />

            @elseif ($type === 'video')
                <x-home.video :section="$section" :href="route('web.video.index', $locale)" />

            @elseif ($type === 'issue')
                <x-home.issue :section="$section" :stats="$issueStats" />

            @elseif ($type === 'companies')
                <x-home.companies :section="$section" :href="route('web.home', $locale)" />

            @elseif ($type === 'newsletter')
                {{-- The band is part of the page chrome; the section only marks
                     where an editor wants it. --}}

            @else
                {{-- A section type added to the enum before it has a design of
                     its own still renders, as a plain card grid. --}}
                <x-home.cards :section="$section" :cols="3" />
            @endif
            </div>
        @empty
            <p class="py-20 text-center text-ink-3">لا يوجد محتوى منشور بعد.</p>
        @endforelse
    </x-ui.container>
</x-layout.public>
