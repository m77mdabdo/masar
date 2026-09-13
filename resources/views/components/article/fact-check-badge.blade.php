@props(['article', 'tone' => 'light'])
{{-- contrast-safe: mint only under tone=dark, which the hero scrim backs. Mint is 2.11:1 on cream and 7.78:1 on g-950. --}}
@if ($article->fact_checked_at)
    {{-- On a photograph the g-600 fill has no reliable contrast, so the dark
         variant uses the mint accent on the scrim, which the hero guarantees. --}}
    <div @class([
        'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm',
        'bg-cream/12 text-mint' => $tone === 'dark',
        'bg-g-600/10 text-g-600' => $tone !== 'dark',
    ])>
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M8 1l5.5 2.5v4C13.5 11 11 13.8 8 15c-3-1.2-5.5-4-5.5-7.5v-4L8 1z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
            <path d="M5.75 7.9L7.2 9.4l3.05-3.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>
            دُقّقت المعلومات
            <time datetime="{{ $article->fact_checked_at->toIso8601String() }}" class="ltr-isolate nums-tabular">
                {{ $article->fact_checked_at->format('Y-m-d') }}
            </time>
        </span>
    </div>
@endif
