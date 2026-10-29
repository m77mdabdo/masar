@php $locale = app()->getLocale(); @endphp
<x-layout.public :title="$topic->name" :description="$topic->translate('description')">
    <x-ui.container class="py-10">
        <header class="mb-10 border-b border-line pb-6">
            <p class="mb-1 text-sm text-ink-3">{{ __('pages.topic.kicker') }}</p>
            <h1 class="font-display text-3xl font-semibold text-g-950 sm:text-4xl">{{ $topic->displayName() }}</h1>
            @if ($topic->translate('description'))
                <p class="mt-3 max-w-2xl text-ink-3">{{ $topic->translate('description') }}</p>
            @endif
            <p class="nums-tabular mt-2 text-sm text-ink-3">{{ trans_choice('site.count.stories', $topic->articles_count) }}</p>
        </header>

        {{-- Who appears in this topic's coverage. The graph answering a question
             a tag cannot: not "what is tagged 2030" but "who is in those
             stories". --}}
        @if ($entities->isNotEmpty())
            <section class="mb-10" aria-labelledby="topic-entities">
                <x-ui.section-rule :title="__('pages.topic.entities')" id="topic-entities" />
                <div class="grid gap-6 sm:grid-cols-2">
                    @foreach ($entities as $type => $rows)
                        <div class="min-w-0">
                            <h3 class="mb-2 text-[0.7rem] uppercase tracking-[0.08em] text-ink-3">
                                {{ $type === 'person' ? __('site.ui.people') : __('site.ui.companies') }}
                            </h3>
                            <ul class="space-y-1.5">
                                @foreach ($rows as $row)
                                    <li class="flex items-baseline justify-between gap-3">
                                        <a class="min-w-0 truncate text-sm text-g-900 underline-offset-2 hover:underline"
                                           href="{{ $type === 'person'
                                               ? route('web.person.show', [$locale, $row['slug']])
                                               : route('web.company.show', [$locale, $row['slug']]) }}"><x-ui.bidi :text="$row['name']" /></a>
                                        <span class="nums-tabular shrink-0 text-xs text-ink-3">{{ trans_choice('site.count.stories', $row['count']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <x-ui.section-rule :title="__('site.ui.latest')" />

        <div class="space-y-6">
            @forelse ($articles as $article)
                <x-article.card-row :article="$article" />
            @empty
                <p class="py-16 text-center text-ink-3">{{ __('pages.topic.empty') }}</p>
            @endforelse
        </div>

        @if ($articles->hasPages())
            <div class="mt-10">{{ $articles->links() }}</div>
        @endif
    </x-ui.container>
</x-layout.public>
