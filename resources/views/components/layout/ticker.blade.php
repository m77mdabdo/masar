@props(['figures', 'href' => null])
{{-- contrast-safe: every foreground here renders on g-950 or g-850. mint-2 is
     11.0:1 on g-950, coral 7.13:1, cream 16.41:1. None is used on cream. --}}
@php $cells = $figures->ticker(); @endphp

@if ($cells->isNotEmpty())
    {{--
        A <nav>, not a <div>: it sits outside <main> as chrome, and content
        outside every landmark cannot be reached by landmark navigation.

        Every cell is an editor-entered figure — value, change and the shape
        points its chart is drawn from — carrying the band's shared "as of".
        There is no feed behind this and there is not meant to be one.
    --}}
    {{-- The band is the darkest surface on the page — near-black green,
         a step below the masthead's own ground. --}}
    <nav aria-label="مؤشرات السوق" class="bg-band text-cream">
        <x-ui.container class="!pe-0">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]">
                @foreach ($cells as $cell)
                    @php
                        $rising = $cell['change'] !== null && $cell['change'] > 0;
                        $falling = $cell['change'] !== null && $cell['change'] < 0;
                    @endphp
                    {{-- The 46px chart costs 14px the cell did not have; it comes out
                         of the padding and the gap rather than out of the figure,
                         which was clipping at the prototype's 24px/11px. --}}
                    <div class="flex h-[3.375rem] items-center gap-2 border-s border-white/[0.07] px-4 sm:px-5">
                        @if ($cell['series'] !== [])
                            {{-- The line is the row's own points. A row saved
                                 without them gets no chart, rather than a curve
                                 this template invented to fill the space. --}}
                            <span @class([
                                // Two cells to a row on a phone leave no space
                                // for a chart beside a six-figure print; the
                                // figure is the part that has to survive.
                                'hidden h-[1.375rem] w-[2.875rem] shrink-0 opacity-85 sm:block',
                                'text-mint' => $rising,
                                'text-coral' => $falling,
                                'text-cream/45' => ! $rising && ! $falling,
                            ]) aria-hidden="true">
                                <x-data.sparkline :points="$cell['series']" :height="22" :width="46" :stroke="1.4" />
                            </span>
                        @endif

                        <span class="min-w-0">
                            <span class="block truncate font-display text-[0.5625rem] font-bold uppercase leading-tight tracking-[0.12em] text-mint-2">
                                {{ $cell['label'] }}
                            </span>
                            <bdi class="nums-tabular block font-display text-[0.9375rem] font-semibold tracking-[0.01em] text-white">{{ $cell['value'] }}</bdi>
                        </span>

                        @if ($cell['change'] !== null)
                            <span @class([
                                // Immediately after the figure, not pushed to
                                // the cell's far edge: the change belongs to
                                // the print it follows, and a gap between them
                                // reads as two separate readings.
                                'nums-tabular shrink-0 whitespace-nowrap text-[0.75rem] font-semibold',
                                'text-mint' => $rising,
                                'text-coral' => $falling,
                                'text-cream/55' => ! $rising && ! $falling,
                            ])>
                                {{-- A flat print carries no glyph: an arrow
                                     that points nowhere is still an arrow. --}}
                                @if ($rising || $falling)
                                    <span aria-hidden="true">{{ $rising ? '▲' : '▼' }}</span>
                                @endif
                                <bdi>{{ number_format(abs($cell['change']), 2) }}%</bdi>
                                <span class="sr-only">{{ $rising ? 'ارتفاع' : ($falling ? 'انخفاض' : 'بلا تغير') }}</span>
                            </span>
                        @endif
                    </div>
                @endforeach

                {{-- The cell that terminates the band, one step lighter than it. --}}
                @if ($href)
                    <a href="{{ $href }}"
                       class="flex h-[3.375rem] items-center gap-3 border-s border-white/[0.07] bg-g-850 px-[1.625rem] font-display text-[0.6875rem] font-bold uppercase tracking-[0.12em] text-white transition hover:bg-g-700">
                        <span>نبض السوق</span>
                        <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
                    </a>
                @endif
            </div>
        </x-ui.container>

        {{-- A caption on the band, not a bar under it: same ground, no rule
             above it, 9px, 18px tall. It rode inside the band as a seventh cell
             for a while, which was worse — it took 168px of 1300 and left the
             five instrument cells at 192px, where every value clipped. The band
             holds the instruments or the caption at this width, not both, and
             the instruments are what a reader came for.

             It cannot be dropped or hidden behind a hover: a figure without a
             visible date and source does not publish. --}}
        <x-ui.container>
            <div class="flex min-h-[1.125rem] items-center pb-0.5">
                <x-data.as-of :figures="$figures" tone="dark" class="!text-[0.5625rem] !leading-[1.2] !text-cream/55" />
            </div>
        </x-ui.container>
    </nav>
@endif
