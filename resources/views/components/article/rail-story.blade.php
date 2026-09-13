@props(['article'])
@php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
{{-- The rail's own card weight: a 74px thumb, the category and reading time on
     one line, and a headline. Narrower than card-standard and quieter than it. --}}
<article class="group grid grid-cols-[4.5rem_1fr] items-start gap-3 border-b border-line px-4 py-3 last:border-0">
    <a href="{{ $url }}" class="block overflow-hidden rounded bg-line" tabindex="-1" aria-hidden="true">
        <x-ui.responsive-image
            scale="thumb"
            :media="$article->heroMedia"
            :alt="$article->hero_alt ?? ''"
            ratio="card"
            sizes="72px"
            class="transition duration-500 group-hover:scale-[1.05]"
        />
    </a>

    <div class="min-w-0">
        <p class="flex flex-wrap items-center gap-x-2 text-[0.7rem] text-ink-3">
            @if ($article->category)
                <span class="font-medium uppercase tracking-wide text-g-600">{{ $article->category->name }}</span>
            @endif
            <span class="nums-tabular">{{ $article->reading_time }} د</span>
        </p>
        <h3 class="mt-1 font-display text-[0.82rem] font-medium text-g-950">
            <a href="{{ $url }}" class="block leading-6 transition hover:text-g-700">{{ $article->title }}</a>
        </h3>
    </div>
</article>
