@php $locale = app()->getLocale(); @endphp
<x-layout.public :title="$person->name" :description="$person->translate('bio')">
    <x-ui.container class="py-10">
        <header class="mb-10 flex flex-wrap items-start gap-5 border-b border-line pb-6">
            @if ($person->photo_path)
                <img src="{{ $person->photo_path }}" alt="" width="72" height="72" class="h-18 w-18 rounded-full object-cover" />
            @endif
            <div class="min-w-0">
                <h1 class="font-display text-3xl font-semibold text-g-950">{{ $person->name }}</h1>
                <p class="mt-1 text-ink-3">{{ $person->translate('title') }}</p>
                @if ($person->company?->name)
                    <a href="{{ app(App\Support\EntityUrl::class)->for($person->company) }}" class="mt-1 inline-block text-sm text-g-700 hover:underline">
                        {{ $person->company->name }}
                    </a>
                @endif
                @if ($person->translate('bio'))
                    <p class="mt-3 max-w-2xl leading-relaxed text-ink-3">{{ $person->translate('bio') }}</p>
                @endif
            </div>
        </header>

        {{-- What this person is actually quoted on. A byline count says nothing;
             the sections they turn up in say what they are a source about. --}}
        @if ($sections->isNotEmpty())
            <section class="mb-10" aria-labelledby="person-sections">
                <x-ui.section-rule title="يتحدث في" id="person-sections" />
                <div class="flex flex-wrap gap-2">
                    @foreach ($sections as $section)
                        <a href="{{ route('web.category.show', [$locale, $section['slug']]) }}"
                           class="inline-flex items-baseline gap-2 rounded-full border border-line bg-white px-3 py-1.5 text-sm transition hover:border-g-600">
                            <span class="text-ink">{{ $section['name'] }}</span>
                            <span class="nums-tabular font-display font-semibold text-g-700">{{ $section['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Who they appear beside. For a person the companies are the useful
             half — an expert quoted repeatedly alongside one company is a fact
             about that expert a reader should be able to see. --}}
        @if ($coMentions->isNotEmpty())
            <section class="mb-10" aria-labelledby="person-co-mentions">
                <x-ui.section-rule title="يظهر مع" id="person-co-mentions" />
                <div class="grid gap-6 sm:grid-cols-2">
                    @foreach ($coMentions as $type => $rows)
                        <div>
                            <h3 class="mb-2 text-[0.7rem] uppercase tracking-[0.08em] text-ink-3">
                                {{ $type === 'person' ? 'أشخاص' : ($type === 'company' ? 'شركات' : 'موضوعات') }}
                            </h3>
                            <ul class="space-y-1.5">
                                @foreach ($rows as $row)
                                    <li class="flex items-baseline justify-between gap-3">
                                        <a class="truncate text-sm text-g-900 underline-offset-2 hover:underline"
                                           href="{{ $type === 'person'
                                               ? route('web.person.show', [$locale, $row['slug']])
                                               : ($type === 'company'
                                                   ? route('web.company.show', [$locale, $row['slug']])
                                                   : route('web.topic.show', [$locale, $row['slug']])) }}">{{ $row['name'] }}</a>
                                        <span class="nums-tabular shrink-0 text-xs text-ink-3">{{ $row['count'] }} مادة</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <x-ui.section-rule title="ورد في" />

        <div class="space-y-6">
            @forelse ($mentions as $mention)
                @php $item = $mention->mentionable; @endphp
                @continue (! $item instanceof App\Models\Article)
                <x-article.card-row :article="$item" />
            @empty
                <p class="py-16 text-center text-ink-3">لا توجد مواد بعد.</p>
            @endforelse
        </div>

        @if ($mentions->hasPages())
            <div class="mt-10">{{ $mentions->links() }}</div>
        @endif
    </x-ui.container>
</x-layout.public>
