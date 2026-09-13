@props(['section', 'figures', 'opportunity' => null, 'href' => null, 'media' => null])
{{-- contrast-safe: mint appears only inside the g-950 opportunity card. Mint is
     2.11:1 on cream and 7.78:1 on g-950. --}}
@php
    // Tabs group the section's own articles by content type — the grouping an
    // editor already made, not a second taxonomy to maintain.
    $tabs = $section['items']->groupBy(fn ($a) => $a->content_type?->label() ?? 'تحليل');
    $first = $tabs->keys()->first();
    $pulse = $figures->pulse();
@endphp

<section class="mb-0" aria-labelledby="intelligence-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" id="intelligence-heading" />

    {{-- Light, with only the opportunity card dark. --}}
    <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
        <div x-data="{ tab: @js($first) }">
            @if ($tabs->count() > 1)
                <div class="mb-4 flex flex-wrap gap-6 border-b border-line" role="tablist" aria-label="{{ $section['title'] }}">
                    @foreach ($tabs->keys() as $name)
                        <button
                            type="button" role="tab"
                            :aria-selected="tab === @js($name) ? 'true' : 'false'"
                            :tabindex="tab === @js($name) ? 0 : -1"
                            @click="tab = @js($name)"
                            :class="tab === @js($name)
                                ? 'border-g-900 text-g-950'
                                : 'border-transparent text-ink-3 hover:text-g-700'"
                            class="-mb-px border-b-2 pb-2.5 font-display text-[0.72rem] font-semibold uppercase tracking-[0.1em] transition"
                        >{{ $name }}</button>
                    @endforeach
                </div>
            @endif

            <div class="rounded-xl border border-line bg-white p-5">
                <div class="grid gap-5 sm:grid-cols-[13.75rem_1fr]">
                    <div>
                        @if ($pulse->isNotEmpty())
                            <ul>
                                @foreach ($pulse as $row)
                                    <li class="flex items-center justify-between gap-3 border-b border-line py-2.5 first:pt-0 last:border-0">
                                        <span class="flex min-w-0 items-center gap-2 text-sm">
                                            <span @class([
                                                'h-2 w-2 shrink-0 rounded-sm',
                                                'bg-g-600' => ($row['change'] ?? 0) >= 0,
                                                'bg-gold-ink' => ($row['change'] ?? 0) < 0,
                                            ]) aria-hidden="true"></span>
                                            <span class="truncate">{{ $row['label'] }}</span>
                                        </span>
                                        <x-data.figure :value="$row['value']" :change="$row['change']" size="sm" />
                                    </li>
                                @endforeach
                            </ul>
                            <x-data.as-of :figures="$figures" class="mt-3" />
                        @else
                            <x-data.empty label="لم تُدخل قراءات المناطق بعد. لا نعرض نسبة بلا مصدر وتاريخ." />
                        @endif
                    </div>

                    {{--
                        The prototype draws a map of the region here. A hand-drawn
                        outline of national borders is a factual claim we cannot
                        stand behind — least of all in this region — so the panel
                        lists the areas we cover instead. It also mirrors, which
                        an imported SVG would not.
                    --}}
                    <div class="rounded-lg bg-cream p-4">
                        <h3 class="mb-2.5 font-display text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-ink-3">مناطق التغطية</h3>
                        {{-- The panel role goes on a wrapper, not on the <ul>:
                             role="tabpanel" on a list replaces its list
                             semantics, and a screen reader stops announcing how
                             many items there are. --}}
                        @foreach ($tabs as $name => $articles)
                            <div role="tabpanel" x-show="tab === @js($name)" x-cloak>
                                <ul class="space-y-2">
                                    @foreach ($articles->take(4) as $article)
                                        <li>
                                            <a href="{{ app(App\Support\EntityUrl::class)->for($article) }}"
                                               class="block text-[0.8rem] leading-6 text-ink transition hover:text-g-700">{{ $article->title }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @if ($opportunity)
            <a href="{{ app(App\Support\EntityUrl::class)->for($opportunity) }}"
               class="group relative isolate flex min-h-[17rem] flex-col justify-end gap-3 overflow-hidden rounded-xl bg-g-950 p-6 text-cream">
                <x-ui.responsive-image
                    :media="$media"
                    alt=""
                    ratio="hero"
                    :fill="true"
                    sizes="(max-width: 1024px) 100vw, 33vw"
                    class="-z-10 opacity-65 transition duration-700 group-hover:scale-[1.03]"
                />
                <div class="card-scrim absolute inset-0 -z-10" aria-hidden="true"></div>

                <span class="font-display text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-mint">فرصة الأسبوع</span>
                <h3 class="font-display text-xl font-semibold leading-snug sm:text-2xl">{{ $opportunity->title }}</h3>
                <p class="text-sm leading-relaxed text-cream/75">{{ Str::limit($opportunity->summary, 130) }}</p>
                <span class="mt-1 inline-flex w-fit items-center gap-2 rounded-full border border-cream/45 px-5 py-2.5 text-xs font-medium transition group-hover:border-mint group-hover:text-mint">
                    استكشف الفرص
                    <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
                </span>
            </a>
        @endif
    </div>
</section>
