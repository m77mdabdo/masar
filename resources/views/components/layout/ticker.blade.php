@props(['figures', 'href' => null])
{{-- contrast-safe: renders on g-900. Mint is 2.11:1 on cream and 7.78:1 on g-950. --}}
@php $cells = $figures->ticker(); @endphp

@if ($cells->isNotEmpty())
    {{--
        A <nav>, not a <div>: it sits outside <main> as chrome, and content
        outside every landmark cannot be reached by landmark navigation.

        Every cell is an editor-entered figure carrying the band's shared "as
        of". There is no feed behind this and there is not meant to be one.
    --}}
    <nav aria-label="مؤشرات السوق" class="border-b border-g-950/40 bg-g-900 text-cream">
        <x-ui.container class="!px-0">
            <div class="grid grid-cols-2 divide-cream/12 sm:grid-cols-3 lg:grid-cols-6 lg:divide-x lg:rtl:divide-x-reverse">
                @foreach ($cells as $cell)
                    <div class="flex h-[3.375rem] items-center gap-3 px-4 sm:px-5">
                        @if ($cell['change'] !== null)
                            <span @class([
                                'h-4 w-8 shrink-0',
                                'text-mint' => $cell['change'] >= 0,
                                'text-gold' => $cell['change'] < 0,
                            ]) aria-hidden="true">
                                {{-- A two-point line is a direction, not a series. --}}
                                <x-data.sparkline :points="[$cell['change'] >= 0 ? 0 : 1, $cell['change'] >= 0 ? 1 : 0]" :height="12" :width="32" />
                            </span>
                        @endif

                        <span class="min-w-0">
                            <span class="block truncate font-display text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-mint">
                                {{ $cell['label'] }}
                            </span>
                            <bdi class="nums-tabular block font-display text-sm font-semibold">{{ $cell['value'] }}</bdi>
                        </span>

                        @if ($cell['change'] !== null)
                            <span @class([
                                'nums-tabular ms-auto shrink-0 text-[0.7rem] font-medium',
                                'text-mint' => $cell['change'] > 0,
                                'text-gold' => $cell['change'] < 0,
                                'text-cream/55' => $cell['change'] == 0,
                            ])>
                                <span aria-hidden="true">{{ $cell['change'] > 0 ? '▲' : ($cell['change'] < 0 ? '▼' : '—') }}</span>
                                <bdi>{{ number_format(abs($cell['change']), 2) }}%</bdi>
                                <span class="sr-only">{{ $cell['change'] > 0 ? 'ارتفاع' : ($cell['change'] < 0 ? 'انخفاض' : 'بلا تغير') }}</span>
                            </span>
                        @endif
                    </div>
                @endforeach

                @if ($href)
                    <a href="{{ $href }}" class="flex h-[3.375rem] items-center justify-between gap-2 bg-g-700/45 px-5 font-display text-[0.7rem] font-semibold uppercase tracking-[0.1em] transition hover:bg-g-700">
                        <span>نبض السوق</span>
                        <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
                    </a>
                @endif
            </div>
        </x-ui.container>

        {{-- The date the whole band is true as of. Not optional. --}}
        <x-ui.container class="pb-1.5">
            <x-data.as-of :figures="$figures" tone="dark" />
        </x-ui.container>
    </nav>
@endif
