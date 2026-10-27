@props(['numbers' => []])
@php
    /*
     * A number the product cannot verify must be labelled, dated, attributed,
     * and must disappear rather than degrade when its basis is missing
     * (CLAUDE.md §5). This band is where that rule bites hardest: it is the
     * article page's only inverted ground, which is the design saying "these
     * are the figures the story turns on".
     *
     * So all four parts are required here, not merely preferred. An entry
     * missing any one of them does not render, and if none survive, neither
     * does the band. Before this, entries carried a value and a label only —
     * and the two came from independent pools in the factory, so the page
     * could state «عدد الشركات المرخصة = 36 مليار ريال» with no source and no
     * date beneath it.
     */
    $numbers = collect($numbers)
        ->filter(fn ($n): bool => filled($n['value'] ?? null)
            && filled($n['label'] ?? null)
            && filled($n['source'] ?? null)
            && filled($n['as_of'] ?? null))
        ->take(4)
        ->values();
@endphp
@if ($numbers->isNotEmpty())
    {{-- A dark band, not four cards: these are the figures the story turns on,
         and giving them the page's only inverted ground is what says so.
         Dividers are start-edge borders so the band mirrors without a rule. --}}
    <div class="not-prose -mx-5 mt-2 bg-g-900 text-cream sm:mx-0 sm:rounded-xl">
        <dl class="grid grid-cols-2 md:grid-cols-4">
            @foreach ($numbers as $number)
                <div class="border-line/15 p-5 [&:not(:first-child)]:border-s">
                    {{-- <bdi>, not `ltr-isolate`. A value is sometimes a bare
                         figure ("+24%") and sometimes a figure inside Arabic
                         ("31 مليار ريال"); isolating without forcing a direction
                         lets the browser resolve each correctly, where a hard
                         direction:ltr reverses the Arabic one. --}}
                    {{-- The prototype's values are short ("$123B"). Ours can be
                         an Arabic phrase with a figure in it, so the size steps
                         down rather than wrapping to three lines. --}}
                    <dd @class([
                        'text-balance font-display font-semibold text-cream',
                        'text-2xl sm:text-3xl' => mb_strlen((string) $number['value']) <= 8,
                        'text-xl sm:text-2xl' => mb_strlen((string) $number['value']) > 8,
                    ])>
                        <bdi class="nums-tabular">{{ $number['value'] }}</bdi>
                    </dd>
                    <dt class="mt-2 text-xs leading-relaxed text-cream/65">{{ $number['label'] }}</dt>

                    {{-- Source and date in the same breath as the figure, not in
                         a footnote a reader has to hunt for. cream/50 is 6.4:1
                         on g-900 — quiet, not unreadable.
                         contrast-safe: on --g-900. --}}
                    <p class="mt-1.5 text-[0.65rem] leading-5 text-cream/50">
                        {{ $number['source'] }}
                        <span aria-hidden="true">·</span>
                        <time datetime="{{ $number['as_of'] }}" class="ltr-isolate nums-tabular">{{ $number['as_of'] }}</time>
                    </p>
                </div>
            @endforeach
        </dl>
    </div>
@endif
