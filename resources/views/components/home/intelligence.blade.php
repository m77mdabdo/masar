@props(['section', 'figures', 'opportunity' => null, 'href' => null, 'media' => null])
{{-- contrast-safe: mint appears only inside the g-950 opportunity card. Mint is
     2.07:1 on cream and 7.63:1 on g-950. --}}
@php
    use App\Support\EntityUrl;

    // Four fixed panels, because they are the section's editorial shape rather
    // than a view of whatever content types happen to exist this week. Each one
    // is backed by data we already hold, and each renders the labelled empty
    // state when that data has not been entered — the tab never disappears and
    // never invents a number to fill itself.
    $urls = app(EntityUrl::class);
    $pulse = $figures->pulse();
    $keyNumbers = $figures->data();
    $sectors = $section['items']->groupBy(fn ($a) => $a->category?->name ?? 'تحليل');
    $radar = $section['items']->filter(fn ($a) => filled($a->opportunity))->values();

    $panels = [
        'pulse' => 'نبض السوق',
        'sectors' => 'قطاعات صاعدة',
        'radar' => 'رادار الفرص',
        'numbers' => 'أرقام رئيسية',
    ];
@endphp

<section class="mb-0" aria-labelledby="intelligence-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" id="intelligence-heading" />

    <p class="-mt-3 mb-5 text-[0.9rem] leading-relaxed text-ink-3">رؤى حقيقية. فرص حقيقية.</p>

    {{-- Light, with only the opportunity card dark. --}}
    <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
        <div x-data="{ tab: 'pulse' }">
            <div class="mb-4 flex flex-wrap gap-6 border-b border-line" role="tablist" aria-label="{{ $section['title'] }}">
                @foreach ($panels as $key => $label)
                    <button
                        type="button" role="tab"
                        :aria-selected="tab === @js($key) ? 'true' : 'false'"
                        :tabindex="tab === @js($key) ? 0 : -1"
                        @click="tab = @js($key)"
                        :class="tab === @js($key)
                            ? 'border-g-900 text-g-950'
                            : 'border-transparent text-ink-3 hover:text-g-700'"
                        class="-mb-px border-b-2 pb-2.5 font-display text-[0.72rem] font-semibold uppercase tracking-[0.12em] transition"
                    >{{ $label }}</button>
                @endforeach
            </div>

            <div class="rounded-xl border border-line bg-white p-5">
                {{-- MARKET PULSE --}}
                <div role="tabpanel" x-show="tab === 'pulse'" x-cloak>
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
                            The map is land only, and decorative. Natural Earth
                            1:110m (public domain) dissolved to a single fill
                            with no contrasting stroke, so adjacent countries
                            merge into one silhouette: it draws coastlines and
                            no political border at all, which is the only
                            version of this we can put on the page. Nothing is
                            encoded in it — no labels, no per-country colour, no
                            data. The list in front of it is what carries
                            meaning.

                            It does not mirror in RTL: a mirrored world is a
                            wrong world, not a translated one.
                        --}}
                        <div class="relative isolate overflow-hidden rounded-lg bg-cream p-4">
                            <img src="/svg/world-outline.svg" alt="" aria-hidden="true" loading="lazy" decoding="async"
                                 width="1000" height="480"
                                 class="pointer-events-none absolute inset-0 -z-10 h-full w-full object-cover opacity-[0.28]" />
                            <h3 class="mb-2.5 font-display text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-ink-3">مناطق التغطية</h3>
                            <ul class="space-y-2">
                                @foreach ($section['items']->take(4) as $article)
                                    <li>
                                        <a href="{{ $urls->for($article) }}"
                                           class="block text-[0.8rem] font-medium leading-6 text-ink transition hover:text-g-700">{{ $article->title }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- TRENDING SECTORS --}}
                <div role="tabpanel" x-show="tab === 'sectors'" x-cloak>
                    @if ($sectors->isNotEmpty())
                        <ul class="grid gap-x-8 gap-y-2.5 sm:grid-cols-2">
                            @foreach ($sectors as $name => $articles)
                                <li class="border-b border-line pb-2.5 last:border-0">
                                    <span class="block font-display text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gold-ink">{{ $name }}</span>
                                    <a href="{{ $urls->for($articles->first()) }}"
                                       class="block text-[0.85rem] font-medium leading-6 text-ink transition hover:text-g-700">{{ $articles->first()->title }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <x-data.empty label="لا توجد قطاعات مرصودة في هذه الفترة." />
                    @endif
                </div>

                {{-- OPPORTUNITY RADAR --}}
                <div role="tabpanel" x-show="tab === 'radar'" x-cloak>
                    @if ($radar->isNotEmpty())
                        <ul class="space-y-3">
                            @foreach ($radar->take(4) as $article)
                                <li class="grid grid-cols-[auto_1fr] gap-3 border-b border-line pb-3 last:border-0">
                                    <span class="nums-tabular font-display text-lg font-normal leading-none text-gold-ink" aria-hidden="true">
                                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <span class="min-w-0">
                                        <a href="{{ $urls->for($article) }}"
                                           class="block font-display text-[0.9rem] font-semibold leading-6 text-g-950 transition hover:text-g-700">{{ $article->title }}</a>
                                        <span class="line-clamp-2 text-[0.78rem] leading-6 text-ink-3">{{ $article->opportunity }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <x-data.empty label="لم تُرصد فرص في هذه الفترة." />
                    @endif
                </div>

                {{-- KEY NUMBERS --}}
                <div role="tabpanel" x-show="tab === 'numbers'" x-cloak>
                    @if ($keyNumbers->isNotEmpty())
                        <div class="grid gap-px overflow-hidden rounded-lg bg-line sm:grid-cols-2">
                            @foreach ($keyNumbers as $row)
                                <div class="bg-white p-4">
                                    <span class="block text-[0.75rem] text-ink-3">{{ $row['label'] }}</span>
                                    <x-data.figure :value="$row['value']" :change="$row['change']" />
                                </div>
                            @endforeach
                        </div>
                        <x-data.as-of :figures="$figures" class="mt-3" />
                    @else
                        <x-data.empty label="لم تُدخل أرقام بعد. لا نعرض رقمًا بلا مصدر وتاريخ." />
                    @endif
                </div>
            </div>
        </div>

        @if ($opportunity)
            {{-- The feature, not a card: dark editorial ground with the
                 photograph entering on the end side behind a slanted seam. The
                 image sits under a fade rather than against a hard cut, so it
                 reads as part of the panel. --}}
            <a href="{{ app(App\Support\EntityUrl::class)->for($opportunity) }}"
               class="group relative isolate flex min-h-[22rem] flex-col justify-center gap-4 overflow-hidden rounded-xl bg-g-950 p-8 text-cream">
                <span class="diagonal-edge absolute inset-y-0 end-0 -z-10 w-[62%] overflow-hidden">
                    <x-ui.responsive-image
                        :media="$media"
                        alt=""
                        ratio="portrait"
                        :fill="true"
                        sizes="(max-width: 1024px) 60vw, 22vw"
                        class="h-full w-full transition duration-700 group-hover:scale-[1.04]"
                    />
                </span>
                <span class="diagonal-fade absolute inset-0 -z-10" aria-hidden="true"></span>

                <span class="max-w-[22ch]">
                    <span class="block font-display text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-mint">فرصة الأسبوع</span>
                    <h3 class="mt-3 font-display text-[1.75rem] font-semibold leading-[1.22]">{{ $opportunity->title }}</h3>
                    <p class="mt-3 text-[0.9rem] leading-[1.7] text-cream/75">{{ Str::limit($opportunity->summary, 120) }}</p>
                </span>

                <span class="mt-2 inline-flex w-fit items-center gap-2 rounded-full border border-cream/45 px-5 py-2.5 text-xs font-medium transition group-hover:border-mint group-hover:text-mint">
                    استكشف الفرص
                    <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
                </span>
            </a>
        @endif

    </div>
</section>
