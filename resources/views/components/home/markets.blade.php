@props(['section', 'figures', 'href' => null])
@php
    $items = $section['items'];
    $insights = $items->take(5);
    $index = $figures->index();
    $instruments = $figures->instruments();
@endphp

<section class="mb-0" aria-labelledby="markets-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" more="نبض السوق" id="markets-heading" />

    <div class="grid gap-8 lg:grid-cols-[1.25fr_0.75fr] lg:gap-9">
        <div class="rounded-xl border border-line bg-white p-5">
            @if ($index)
                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-3">
                    <div class="flex flex-wrap items-baseline gap-3">
                        <b class="font-display text-[0.95rem] font-semibold text-g-950">{{ $index['label'] }}</b>
                        <x-data.figure :value="$index['value']" :change="$index['change']" size="sm" />
                    </div>
                    <x-data.as-of :figures="$figures" />
                </div>

                @if ($index['series'] !== [])
                    {{-- No value axis: the points are an editor-entered shape,
                         not a series we can stand behind to the pixel. --}}
                    <div class="h-40 text-g-600" role="img" aria-label="اتجاه {{ $index['label'] }}">
                        <x-data.sparkline :points="$index['series']" :area="true" :height="170" :width="600" />
                    </div>
                @endif
            @else
                <x-data.empty label="لم يُدخل محرّر قيمة المؤشر بعد. لا نعرض رقمًا بلا مصدر وتاريخ." />
            @endif

            @if ($instruments->isNotEmpty())
                <div class="mt-5 grid grid-cols-2 gap-px border border-line bg-line">
                    @foreach ($instruments as $row)
                        <div class="bg-white p-4">
                            <div class="font-display text-[0.7rem] font-semibold uppercase tracking-[0.1em] text-ink-3">{{ $row['label'] }}</div>
                            <x-data.figure :value="$row['value']" :change="$row['change']" size="lg" class="mt-2" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h3 class="mb-3 font-display text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-g-950">قراءات في السوق</h3>

            <ol>
                @foreach ($insights as $article)
                    @php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
                    <li class="grid grid-cols-[1.625rem_1fr] gap-3 border-b border-line py-3 first:pt-0 last:border-0">
                        <span class="nums-tabular font-display text-lg leading-6 text-ink-3" aria-hidden="true">{{ $loop->iteration }}</span>
                        <a href="{{ $url }}" class="block text-[0.85rem] leading-6 text-ink transition hover:text-g-700">{{ $article->title }}</a>
                    </li>
                @endforeach
            </ol>

            @if ($href)
                <a href="{{ $href }}" class="mt-4 inline-flex items-center gap-2 rounded-full border border-line px-5 py-2.5 text-xs font-medium text-g-900 transition hover:border-g-600">
                    افتح صفحة الأسواق
                    <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
                </a>
            @endif
        </div>
    </div>
</section>
