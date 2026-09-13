@props(['article'])
@php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
{{-- The secondary leads. Text first, a small square crop at the end — the
     opposite arrangement to every other card, which is what stops the strip
     reading as a fourth row of the grid above it. --}}
<article class="group grid grid-cols-[1fr_5.75rem] items-start gap-4 bg-cream p-5">
    <div class="min-w-0">
        @if ($article->category)
            <span class="font-display text-[0.7rem] font-semibold uppercase tracking-[0.1em] text-g-600">{{ $article->category->name }}</span>
        @endif
        <h3 class="mt-1.5 font-display text-base font-semibold text-g-950">
            <a href="{{ $url }}" class="block leading-6 transition hover:text-g-700">{{ $article->title }}</a>
        </h3>
        <p class="nums-tabular mt-2 text-[0.7rem] text-ink-3">{{ $article->reading_time }} دقيقة قراءة</p>
    </div>

    <a href="{{ $url }}" class="block overflow-hidden rounded bg-line" tabindex="-1" aria-hidden="true">
        <x-ui.responsive-image
            scale="thumb"
            :media="$article->heroMedia"
            :alt="$article->hero_alt ?? ''"
            ratio="square"
            sizes="92px"
            class="transition duration-700 group-hover:scale-[1.06]"
        />
    </a>
</article>
