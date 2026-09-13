@php
    use App\Enums\ArticleSection;

    $locale = app()->getLocale();
    $urls = app(App\Support\EntityUrl::class);
    $canonical = $article->canonical_url ?: $urls->for($article, $locale, true);

    // The floating quote beside the hero is a pullquote — a short evocative
    // line. The sourced quote with a citation stays in the body, inside "why it
    // matters", where the prototype puts it. Without a pullquote the hero
    // borrows a quote only when the body can spare one.
    $pullquotes = $article->blocks->filter(
        fn ($b): bool => $b->type === 'pullquote' && filled($b->data['text'] ?? null)
    );
    $quotes = $article->blocks->filter(
        fn ($b): bool => $b->type === 'quote' && filled($b->data['text'] ?? null)
    );
    $heroQuote = $pullquotes->first() ?? ($quotes->count() > 1 ? $quotes->first() : null);

    // Expert insight is its own band between the key numbers and the related
    // companies, not a card that lands wherever its block happens to sit.
    $expert = $article->blocks->first(
        fn ($b): bool => $b->type === 'expert' && filled($b->data['text'] ?? null)
    );

    // The table of contents lists the four questions the article actually
    // answers, then the bands below them. A heading with nothing under it is
    // not an entry.
    $present = collect(ArticleSection::cases())
        ->filter(fn ($s): bool => $article->blocks->contains(fn ($b): bool => $b->section === $s))
        ->values();

    $tocExtras = collect([
        filled($article->key_numbers) ? ['href' => '#key-numbers', 'label' => 'أرقام مفتاحية'] : null,
        $expert ? ['href' => '#expert-insight', 'label' => 'رأي خبير'] : null,
        $companies->isNotEmpty() ? ['href' => '#related-companies', 'label' => 'شركات ذات صلة'] : null,
        $article->sources->isNotEmpty() ? ['href' => '#sources', 'label' => 'المصادر والتدقيق'] : null,
    ])->filter()->values()->all();

    $heroSizes = '100vw';

    $alternates = $article->translations()->get()
        ->mapWithKeys(fn ($t): array => [$t->locale => $urls->for($t, $t->locale, true)])
        ->put($locale, $canonical)
        ->all();
@endphp

<x-layout.public
    :title="$article->meta_title ?: $article->title"
    :description="$article->meta_description ?: $article->subtitle"
    :image="$article->heroMedia ? App\Support\MediaConversions::socialSrc($article->heroMedia) : $article->og_image_path"
    :canonical="$canonical"
    :noindex="$article->noindex"
    :alternates="$alternates"
    ogType="article"
    :preload="$article->heroMedia ? App\Support\MediaConversions::srcset($article->heroMedia) : null"
    :preloadSizes="$heroSizes"
>
    @push('schema')
        <x-schema.news-article :article="$article" />
        <x-schema.breadcrumbs :items="[
            ['name' => setting('identity.site_name'), 'url' => route('web.home', $locale, true)],
            ['name' => $article->category?->name, 'url' => $urls->for($article->category, $locale, true)],
            ['name' => $article->title, 'url' => $canonical],
        ]" />
    @endpush

    <article>
        <x-article.hero :article="$article" :url="$canonical" :quote="$heroQuote" :sizes="$heroSizes" />

        <x-article.summary-box :points="$article->summary ?? []" />

        <x-ui.container class="mt-10">
            {{--
                Three columns: contents on the start side, body in the centre,
                context on the end side. Both rails collapse below lg — on a
                phone a sticky rail is a screen of furniture before the article
                starts, so the body comes first and the rails follow it.
            --}}
            <div class="mx-auto grid max-w-[1280px] gap-10 lg:grid-cols-[13rem_minmax(0,1fr)_19rem] lg:gap-11">
                <aside class="order-2 hidden lg:order-none lg:block">
                    <div class="sticky top-24 space-y-6">
                        <x-article.toc :article="$article" :sections="$present" :extras="$tocExtras" />

                        {{-- No photograph: the article's own hero is the only
                             one to hand, and the same picture twice on one page
                             reads as a mistake. --}}
                        <x-article.dark-card
                            eyebrow="نشرة مسار"
                            title="اقرأ ما تغيّر فعليًا"
                            body="قراءة أسبوعية للاقتصاد السعودي، في بريدك."
                            :href="route('web.newsletter', $locale)"
                            cta="اشترك"
                        />
                    </div>
                </aside>

                <div class="order-1 min-w-0 lg:order-none">
                    <x-article.body :article="$article" :exclude="$heroQuote" />

                    @if (filled($article->key_numbers))
                        <x-article.band-heading
                            title="أرقام مفتاحية"
                            id="key-numbers"
                            icon="M4 4h16v16H4zM4 10h16M10 4v16"
                        />
                        <x-article.key-numbers :numbers="$article->key_numbers" />
                    @endif

                    @if ($expert)
                        <x-article.band-heading title="رأي خبير" id="expert-insight" :nav="true"
                            icon="M5 13l4 4L19 7" />
                        <x-article.expert-insight
                            :person="App\Models\Person::find($expert->data['person_id'] ?? null)"
                            :text="$expert->data['text']"
                        />
                    @endif

                    @if ($companies->isNotEmpty())
                        <x-article.band-heading
                            title="شركات ذات صلة"
                            id="related-companies"
                            icon="M7 17L17 7m0 0H9m8 0v8"
                            :href="route('web.home', $locale)"
                            more="الدليل"
                        />
                        <x-article.related-companies :companies="$companies" />
                    @endif

                    @if ($article->sources->isNotEmpty())
                        <x-article.band-heading
                            title="المصادر والتدقيق"
                            id="sources"
                            icon="M12 3v18M5 7h14M7 7l-3 7h6zM17 7l-3 7h6z"
                        />
                        <x-article.source-list :sources="$article->sources" :article="$article" />
                    @endif

                    @if ($article->topics->isNotEmpty())
                        <div class="mt-8 flex flex-wrap items-center gap-2 border-t border-line pt-6">
                            <span class="text-xs text-ink-3">الوسوم:</span>
                            @foreach ($article->topics as $topic)
                                <x-ui.tag :href="$urls->for($topic)">{{ $topic->name }}</x-ui.tag>
                            @endforeach
                        </div>
                    @endif

                    {{-- End-aligned, as the prototype has it: the share bar is a
                         closing action, not a second heading. --}}
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <span class="text-xs text-ink-3">شارك هذه المادة</span>
                        <x-article.share :article="$article" :url="$canonical" variant="icons" />
                    </div>
                </div>

                <aside class="order-3 space-y-5 lg:order-none">
                    @if ($related->isNotEmpty())
                        <x-article.rail-panel title="قصص ذات صلة" id="rail-related-heading">
                            @foreach ($related->take(4) as $item)
                                <x-article.rail-story :article="$item" />
                            @endforeach
                        </x-article.rail-panel>
                    @endif

                    @if ($exploreCategory)
                        <x-article.dark-card
                            :media="$exploreMedia"
                            eyebrow="استكشف"
                            :title="$exploreCategory->name"
                            :body="Str::limit($exploreCategory->translate('description'), 90)"
                            :href="$urls->for($exploreCategory)"
                            cta="افتح القسم"
                        />
                    @endif

                    @if ($trendingTopics->isNotEmpty())
                        <x-article.rail-panel title="موضوعات رائجة" id="rail-topics-heading">
                            <ul class="divide-y divide-line">
                                @foreach ($trendingTopics as $topic)
                                    <li>
                                        <a href="{{ $urls->for($topic) }}"
                                           class="flex items-center gap-2 px-4 py-2.5 text-sm text-ink transition hover:text-g-700">
                                            <span class="text-g-600" aria-hidden="true">#</span>
                                            <span class="min-w-0 truncate">{{ $topic->name }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </x-article.rail-panel>
                    @endif

                    @if ($sponsored)
                        {{-- Labelled before it is read, not after. A sponsored
                             slot a reader has to identify for themselves is not
                             disclosed. --}}
                        <div>
                            <p class="mb-2 font-display text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-gold-ink">
                                محتوى مدفوع{{ $sponsored->sponsor_name ? ' — '.$sponsored->sponsor_name : '' }}
                            </p>
                            <x-article.dark-card
                                :media="$sponsored->heroMedia"
                                :title="$sponsored->title"
                                :href="$urls->for($sponsored)"
                                :wordmark="true"
                            />
                        </div>
                    @endif
                </aside>
            </div>
        </x-ui.container>

        @if ($related->isNotEmpty())
            <x-ui.container class="mt-16 border-t border-line pt-8">
                <x-article.band-heading title="اقرأ أيضًا" :nav="true" />
                <x-ui.grid :cols="4">
                    @foreach ($related->take(4) as $item)
                        <x-article.card-overlay :article="$item" />
                    @endforeach
                </x-ui.grid>
            </x-ui.container>
        @endif
    </article>
</x-layout.public>
