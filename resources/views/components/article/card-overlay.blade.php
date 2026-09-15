@props(['article', 'size' => 'sm', 'ratio' => null, 'play' => false, 'meta' => null, 'level' => 3, 'eager' => false])
{{-- contrast-safe: renders on the card scrim, which guarantees a g-950 ground.
     Mint is 2.07:1 on cream and 7.63:1 on g-950. --}}
@php
    $url = app(App\Support\EntityUrl::class)->for($article);

    // One component, three scales. `sm` is the four-across row under an article;
    // `lg` is a section's lead photograph; `xl` is the video poster. They differ
    // in crop, type size and whether a standfirst survives — not in structure.
    $box = $ratio ?? ['sm' => 'card', 'lg' => 'hero', 'xl' => 'hero'][$size];
    $minHeight = ['sm' => 'min-h-[11rem]', 'lg' => 'min-h-[20rem]', 'xl' => 'min-h-[24rem]'][$size];
    $heading = [
        'sm' => 'text-sm sm:text-[0.95rem]',
        'lg' => 'text-xl sm:text-2xl',
        'xl' => 'text-2xl sm:text-[1.75rem]',
    ][$size];
@endphp

<article class="group relative isolate overflow-hidden rounded-xl bg-g-950">
    <a href="{{ $url }}" class="block">
        <x-ui.responsive-image
            scale="card"
            :media="$article->heroMedia"
            :alt="$article->hero_alt ?? ''"
            :eager="$eager"
            :ratio="$box"
            :fill="true"
            sizes="{{ $size === 'sm' ? '(max-width: 640px) 100vw, 25vw' : '(max-width: 1024px) 100vw, 60vw' }}"
            class="-z-10 opacity-80 transition duration-700 group-hover:scale-[1.04] group-hover:opacity-90"
        />
        <div class="card-scrim absolute inset-0 -z-10" aria-hidden="true"></div>

        @if ($play)
            {{-- A poster, not a player: nothing is fetched until the reader
                 reaches the page this links to. --}}
            <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-cream/92 text-g-900 shadow-lg transition group-hover:scale-105">
                    <svg class="h-6 w-6 ms-1 rtl:ms-0 rtl:me-1 rtl:-scale-x-100" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                </span>
            </span>
        @endif

        <div class="{{ $minHeight }} flex flex-col justify-end gap-2 p-4 text-cream sm:p-5">
            @if ($article->category)
                <span class="w-fit rounded-full border border-cream/35 px-2.5 py-0.5 text-[0.7rem] font-medium">
                    {{ $article->category->name }}
                </span>
            @endif

            {{-- The level is a prop: this card is a page's lead on one template
                 and one of four in a row on another, and a heading that skips a
                 level is a broken outline either way. --}}
            <h{{ $level }} class="font-display font-semibold leading-snug {{ $heading }}">{{ $article->title }}</h{{ $level }}>

            @if ($size !== 'sm' && $article->subtitle)
                <p class="max-w-[46ch] text-sm leading-relaxed text-cream/75">{{ Str::limit($article->subtitle, 110) }}</p>
            @endif

            @if ($size !== 'sm')
                <div class="mt-1 flex items-center justify-between gap-3">
                    <span class="nums-tabular text-xs text-cream/70">{{ $meta ?? $article->reading_time.' دقيقة قراءة' }}</span>
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-cream text-g-900 transition group-hover:bg-mint" aria-hidden="true">
                        <svg class="h-3.5 w-3.5 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
                    </span>
                </div>
            @endif
        </div>
    </a>
</article>
