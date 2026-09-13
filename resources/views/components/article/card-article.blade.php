@props(['article', 'compact' => false])
@php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
{{-- The workhorse of the section grids: a bordered card with a 16:9 crop, a
     category chip, a serif headline, and a footer rule carrying the byline.
     Compact drops the standfirst and the author — four across instead of three. --}}
<article class="group flex flex-col overflow-hidden rounded-xl border border-line bg-white">
    <a href="{{ $url }}" class="block bg-line" tabindex="-1" aria-hidden="true">
        <x-ui.responsive-image
            scale="card"
            :media="$article->heroMedia"
            :alt="$article->hero_alt ?? ''"
            ratio="hero"
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 30vw"
            class="transition duration-700 group-hover:scale-[1.03]"
        />
    </a>

    <div class="flex flex-1 flex-col p-4">
        <div class="mb-2 flex flex-wrap items-center gap-2">
            @if ($article->category)
                <span class="rounded bg-mint/25 px-2 py-0.5 text-[0.7rem] font-medium text-g-950">{{ $article->category->name }}</span>
            @endif
            <span class="nums-tabular text-[0.7rem] text-ink-3">{{ $article->reading_time }} دقيقة</span>
        </div>

        <h3 @class(['font-display font-semibold text-g-950', 'text-lg leading-snug' => ! $compact, 'text-base leading-snug' => $compact])>
            <a href="{{ $url }}" class="block leading-6 transition hover:text-g-700">{{ $article->title }}</a>
        </h3>

        @unless ($compact)
            @if ($article->subtitle)
                <p class="mt-2 text-[0.8rem] leading-relaxed text-ink-3">{{ Str::limit($article->subtitle, 105) }}</p>
            @endif
        @endunless

        <div class="mt-auto flex items-center gap-2 border-t border-line pt-3 text-[0.7rem] text-ink-3">
            @unless ($compact)
                @if ($article->author)<span class="truncate font-medium text-ink">{{ $article->author->name }}</span>@endif
            @endunless
            @if ($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}" class="ltr-isolate nums-tabular">
                    {{ $article->published_at->format('Y-m-d') }}
                </time>
            @endif
            <span class="ms-auto flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-g-900 text-cream transition group-hover:bg-g-700" aria-hidden="true">
                <svg class="h-3 w-3 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
            </span>
        </div>
    </div>
</article>
