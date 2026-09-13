@props(['article', 'url', 'quote' => null, 'sizes' => '100vw'])
{{-- contrast-safe: renders on the hero scrim, which guarantees a g-950 ground. Mint is 2.11:1 on cream and 7.78:1 on g-950. --}}
@php
    $urls = app(App\Support\EntityUrl::class);
    $locale = app()->getLocale();
@endphp

{{--
    The headline sits on the photograph, not above it. That means the scrim is
    load-bearing: cream text on an unknown image is only legible because the
    gradient guarantees a dark ground underneath it, whatever the crop.

    The prototype's gradient runs at 72° from the left. Mirrored here — in RTL
    the text is on the right, so the dark end has to be too.
--}}
<section class="relative isolate overflow-hidden bg-g-950 text-cream">
    @if ($article->heroMedia)
        <x-ui.responsive-image
            :media="$article->heroMedia"
            :alt="$article->hero_alt ?? ''"
            ratio="hero"
            :fill="true"
            :eager="true"
            :sizes="$sizes"
            class="-z-10"
        />
    @endif

    <div class="hero-scrim absolute inset-0 -z-10" aria-hidden="true"></div>

    <x-ui.container class="relative flex min-h-[26rem] flex-col justify-end pb-8 pt-10 sm:min-h-[30rem]">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_13rem]">
            <div class="max-w-[46rem]">
                <nav aria-label="مسار التنقل" class="mb-4 flex flex-wrap items-center gap-2 text-xs text-cream/70">
                    <a href="{{ route('web.home', $locale) }}" class="transition hover:text-mint">الرئيسية</a>
                    <span aria-hidden="true">›</span>
                    <a href="{{ $urls->for($article->category) }}" class="transition hover:text-mint">{{ $article->category?->name }}</a>
                    <span aria-hidden="true">›</span>
                    <span class="truncate text-cream/50">{{ Str::limit($article->title, 40) }}</span>
                </nav>

                <div class="mb-4 flex flex-wrap items-center gap-2">
                    @if ($article->category)
                        <a href="{{ $urls->for($article->category) }}"
                           class="inline-flex items-center rounded-full bg-mint/25 px-3 py-1 text-xs font-medium text-cream transition hover:bg-mint/40">
                            {{ $article->category->name }}
                        </a>
                    @endif

                    @if ($article->is_sponsored)
                        <span class="inline-flex items-center rounded-full border border-gold/60 bg-gold/20 px-3 py-1 text-xs font-medium text-cream">
                            محتوى مدفوع{{ $article->sponsor_name ? ' — '.$article->sponsor_name : '' }}
                        </span>
                    @endif
                </div>

                <h1 class="font-display text-3xl font-semibold leading-[1.15] sm:text-4xl lg:text-[3rem]">
                    {{ $article->title }}
                </h1>

                @if ($article->subtitle)
                    <p class="mt-4 max-w-[42rem] text-base leading-relaxed text-cream/80 sm:text-lg">{{ $article->subtitle }}</p>
                @endif

                <x-article.byline :article="$article" :url="$url" tone="dark" class="mt-6" />
            </div>

            @if ($quote)
                {{-- The floating quote. Hidden below lg exactly as the prototype
                     hides it — at that width it would sit under the byline and
                     read as body copy rather than as a pulled line. --}}
                <aside class="hidden self-center lg:block">
                    <blockquote class="font-display text-lg italic leading-relaxed text-cream/90">
                        {{ $quote->data['text'] }}
                    </blockquote>
                    <span class="mt-4 block h-px w-12 bg-mint" aria-hidden="true"></span>
                </aside>
            @endif
        </div>
    </x-ui.container>
</section>
