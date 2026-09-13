@props(['section'])
<section aria-labelledby="rail-podcast-heading">
    <x-ui.section-rule :title="$section['title']" id="rail-podcast-heading" :tight="true" />

    <div>
        @foreach ($section['items']->take(3) as $article)
            @php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
            <article class="group grid grid-cols-[5.25rem_1fr_auto] items-center gap-3.5 border-b border-line py-3 last:border-0">
                <a href="{{ $url }}" class="block overflow-hidden rounded bg-line" tabindex="-1" aria-hidden="true">
                    <x-ui.responsive-image
                        scale="thumb"
                        :media="$article->heroMedia"
                        :alt="$article->hero_alt ?? ''"
                        ratio="square"
                        sizes="84px"
                        class="transition duration-700 group-hover:scale-[1.05]"
                    />
                </a>

                <div class="min-w-0">
                    <h3 class="font-display text-[0.82rem] font-medium text-g-950">
                        <a href="{{ $url }}" class="block leading-6 transition hover:text-g-700">{{ $article->title }}</a>
                    </h3>
                    <span class="nums-tabular text-[0.7rem] text-ink-3">{{ $article->reading_time }} دقيقة</span>
                </div>

                <span class="flex h-8 w-8 items-center justify-center rounded-full border border-line text-g-700 transition group-hover:border-g-600" aria-hidden="true">
                    <svg class="h-3 w-3 ms-0.5 rtl:ms-0 rtl:me-0.5 rtl:-scale-x-100" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                </span>
            </article>
        @endforeach
    </div>
</section>
