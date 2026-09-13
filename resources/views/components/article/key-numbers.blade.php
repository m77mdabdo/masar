@props(['numbers' => []])
@php $numbers = collect($numbers)->filter(fn ($n): bool => filled($n['value'] ?? null))->take(4)->values(); @endphp
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
                    <dt class="mt-2 text-xs leading-relaxed text-cream/65">{{ $number['label'] ?? '' }}</dt>
                </div>
            @endforeach
        </dl>
    </div>
@endif
