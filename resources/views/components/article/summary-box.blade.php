@props(['points' => []])
@php $points = collect($points)->filter()->take(3)->values(); @endphp
@if ($points->isNotEmpty())
{{--
    The 30-second promise: a reader in a hurry still leaves knowing something.
    A full-width band rather than a card, because it is the boundary between the
    hero and the article — the cell dividers are borders on the start edge, so
    they mirror without a rule per direction.
--}}
<section class="border-y border-line bg-white/60" aria-labelledby="summary-heading">
    <x-ui.container>
        <div class="grid md:grid-cols-[14rem_repeat(3,1fr)]">
            <div class="flex items-start gap-3 py-5 md:pe-6">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-g-900 text-cream" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M4 7h16M4 12h16M4 17h10"/>
                    </svg>
                </span>
                <div>
                    <h2 id="summary-heading" class="font-display text-sm font-semibold text-g-950">في 30 ثانية</h2>
                    <p class="mt-0.5 text-xs leading-relaxed text-ink-3">أهم ما في هذه المادة</p>
                </div>
            </div>

            <ol class="contents">
                @foreach ($points as $point)
                    <li class="flex gap-3 border-t border-line py-5 md:border-s md:border-t-0 md:px-6 md:first:ps-6">
                        <span class="nums-tabular font-display text-sm font-semibold leading-6 text-gold-ink" aria-hidden="true">
                            {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="text-[0.9rem] leading-relaxed text-ink">{{ $point }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </x-ui.container>
</section>
@endif
