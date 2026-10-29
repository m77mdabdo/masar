@props(['article', 'url', 'quote' => null, 'sizes' => '100vw'])
{{-- contrast-safe: renders on the hero scrim, which guarantees a g-950 ground. Mint is 2.07:1 on cream and 7.63:1 on g-950. --}}
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
                <nav aria-label="{{ __('article.breadcrumb') }}" class="mb-4 flex flex-wrap items-center gap-2 text-xs text-cream/70">
                    <a href="{{ route('web.home', $locale) }}" class="inline-flex min-h-6 items-center transition hover:text-mint">{{ __('site.nav.home') }}</a>
                    <span aria-hidden="true">›</span>
                    <a href="{{ $urls->for($article->category) }}" class="inline-flex min-h-6 items-center transition hover:text-mint">{{ $article->category?->displayName() }}</a>
                    <span aria-hidden="true">›</span>
                    <span class="truncate text-cream/50">{{ Str::limit($article->title, 40) }}</span>
                </nav>

                <div class="mb-4 flex flex-wrap items-center gap-2">
                    @if ($article->category)
                        <a href="{{ $urls->for($article->category) }}"
                           class="inline-flex items-center rounded-full bg-mint/25 px-3 py-1 text-xs font-medium text-cream transition hover:bg-mint/40">
                            {{ $article->category->displayName() }}
                        </a>
                    @endif

                    @if ($article->is_sponsored)
                        <span class="inline-flex items-center rounded-full border border-gold/60 bg-gold/20 px-3 py-1 text-xs font-medium text-cream">
                            {{ __('article.sponsored') }}{{ $article->sponsor_name ? ' — '.$article->sponsor_name : '' }}
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

        {{-- The photograph's credit (CLAUDE.md §5: credit every photograph).
             The footer told readers each photo is credited on its story page,
             and for the hero — the one photograph every story has — it was
             not. The media record's credit wins over the article's copy.
             contrast-safe: cream/75 on the g-950 hero ground. --}}
        @php $heroCredit = $article->heroMedia?->getCustomProperty('credit') ?: $article->hero_credit; @endphp
        @if ($article->heroMedia && filled($heroCredit))
            <p class="pb-4 text-end text-[0.6875rem] text-cream/75">
                {{ __('article.photo_credit') }} <bdi>{{ $heroCredit }}</bdi>
            </p>
        @endif
    </x-ui.container>
</section>
