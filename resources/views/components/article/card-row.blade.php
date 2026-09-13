@props(['article'])
@php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
{{-- The list row beside a feature: small enough to stack five of them against
     one photograph without either side winning. --}}
<article class="group grid grid-cols-[4.875rem_1fr] items-center gap-3 border-b border-line py-3 first:pt-0 last:border-0">
    <a href="{{ $url }}" class="block overflow-hidden rounded bg-line" tabindex="-1" aria-hidden="true">
        <x-ui.responsive-image
            scale="thumb"
            :media="$article->heroMedia"
            :alt="$article->hero_alt ?? ''"
            ratio="card"
            sizes="78px"
            class="transition duration-700 group-hover:scale-[1.05]"
        />
    </a>

    <div class="min-w-0">
        @if ($article->category)
            <span class="font-display text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-g-600">{{ $article->category->name }}</span>
        @endif
        <h3 class="mt-0.5 font-display text-[0.85rem] font-medium text-g-950">
            <a href="{{ $url }}" class="block leading-6 transition hover:text-g-700">{{ $article->title }}</a>
        </h3>
    </div>
</article>
