@props(['article'])
@php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
{{-- Wider image, more room to breathe: the success-stories rail runs three of
     these down a column with a hairline between, not a grid of equal cards. --}}
<article class="group grid items-center gap-5 border-b border-line py-5 first:pt-0 last:border-0 sm:grid-cols-[11rem_1fr]">
    <a href="{{ $url }}" class="block overflow-hidden rounded-lg bg-line" tabindex="-1" aria-hidden="true">
        <x-ui.responsive-image
            scale="card"
            :media="$article->heroMedia"
            :alt="$article->hero_alt ?? ''"
            ratio="card"
            sizes="(max-width: 640px) 100vw, 176px"
            class="transition duration-700 group-hover:scale-[1.04]"
        />
    </a>

    <div class="min-w-0">
        @if ($article->category)
            <span class="inline-block rounded bg-mint/25 px-2 py-0.5 text-[0.7rem] font-medium text-g-950">{{ $article->category->name }}</span>
        @endif
        <h3 class="mt-2 font-display text-lg font-semibold text-g-950">
            <a href="{{ $url }}" class="block leading-7 transition hover:text-g-700">{{ $article->title }}</a>
        </h3>
        @if ($article->subtitle)
            <p class="mt-1.5 text-[0.82rem] leading-relaxed text-ink-3">{{ Str::limit($article->subtitle, 95) }}</p>
        @endif
    </div>
</article>
