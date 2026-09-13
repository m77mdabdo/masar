@props(['media' => null, 'eyebrow' => null, 'title', 'body' => null, 'href' => null, 'cta' => null, 'wordmark' => false])
{{-- contrast-safe: renders on the card scrim, which guarantees a g-950 ground. Mint is 2.11:1 on cream and 7.78:1 on g-950. --}}
{{-- The rail's dark photo card — used for "explore" and for the sponsored slot.
     Mint and cream are correct foregrounds here because the scrim guarantees a
     g-950 ground under them whatever the photograph is. --}}
<{{ $href ? 'a' : 'div' }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['group relative isolate block overflow-hidden rounded-xl bg-g-950 text-cream']) }}
>
    @if ($media)
        <x-ui.responsive-image
            :media="$media"
            alt=""
            ratio="portrait"
            :fill="true"
            sizes="280px"
            class="-z-10 opacity-70 transition duration-700 group-hover:scale-[1.03]"
        />
    @endif
    <div class="card-scrim absolute inset-0 -z-10" aria-hidden="true"></div>

    <div class="flex min-h-[13rem] flex-col justify-between gap-4 p-5">
        <div>
            @if ($eyebrow)
                <p class="mb-2 font-display text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-mint">{{ $eyebrow }}</p>
            @endif
            <h2 class="font-display text-xl font-semibold leading-tight">{{ $title }}</h2>
            @if ($body)
                <p class="mt-2 text-xs leading-relaxed text-cream/75">{{ $body }}</p>
            @endif
        </div>

        <div class="flex items-end justify-between gap-3">
            @if ($wordmark)
                <span class="font-display text-base tracking-[0.08em] text-cream/90">{{ setting('identity.site_name') }}</span>
            @endif
            @if ($cta)
                <span class="inline-flex items-center gap-2 rounded-full border border-cream/45 px-4 py-2 text-xs font-medium transition group-hover:border-mint group-hover:text-mint">
                    {{ $cta }}
                    <span aria-hidden="true" class="rtl:inline-block rtl:-scale-x-100">→</span>
                </span>
            @elseif ($wordmark)
                <span class="flex h-8 w-8 items-center justify-center rounded-full border border-cream/40 text-cream" aria-hidden="true">
                    <svg class="h-3 w-3 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
                </span>
            @endif
        </div>
    </div>
</{{ $href ? 'a' : 'div' }}>
