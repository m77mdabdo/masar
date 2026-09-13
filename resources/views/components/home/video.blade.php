@props(['section', 'href' => null])
@php
    $items = $section['items'];
    $featured = $items->first();
    $rest = $items->skip(1)->take(5);
@endphp
{{-- A poster with a play control, exactly as the prototype has it — and it is
     also what keeps the front page free of video bytes. The clip itself only
     loads on the article page, behind a deliberate press. --}}
<section class="mb-0" aria-labelledby="video-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" more="كل المرئيات" id="video-heading" />

    <div class="grid gap-8 lg:grid-cols-[1.4fr_1fr] lg:gap-9">
        @if ($featured)
            <x-article.card-overlay :article="$featured" size="xl" :play="true" />
        @endif

        <div>
            @foreach ($rest as $article)
                @php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
                <article class="group grid grid-cols-[6.5rem_1fr] items-center gap-3.5 border-b border-line py-2.5 first:pt-0 last:border-0">
                    <a href="{{ $url }}" class="relative block overflow-hidden rounded bg-line" tabindex="-1" aria-hidden="true">
                        <x-ui.responsive-image
                            scale="thumb"
                            :media="$article->heroMedia"
                            :alt="$article->hero_alt ?? ''"
                            ratio="card"
                            sizes="104px"
                            class="transition duration-700 group-hover:scale-[1.05]"
                        />
                        <span class="absolute inset-0 flex items-center justify-center text-cream" aria-hidden="true">
                            <svg class="h-4 w-4 drop-shadow" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        </span>
                    </a>

                    <div class="min-w-0">
                        <h3 class="font-display text-[0.82rem] font-medium text-g-950">
                            <a href="{{ $url }}" class="block leading-6 transition hover:text-g-700">{{ $article->title }}</a>
                        </h3>
                        <span class="nums-tabular text-[0.7rem] text-ink-3">{{ $article->reading_time }} دقيقة</span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
