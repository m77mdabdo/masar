@props(['section', 'backdrop' => null, 'sizes' => '(max-width: 1024px) 100vw, 96vw'])
{{-- contrast-safe: every foreground sits on the scrimmed photograph, so the
     ground is the picture and not a token. Measured off the rendered pixels of
     all three demo slides, worst line box of each run: headline 7.65:1,
     standfirst 7.67:1, byline 6.4:1, step label 12.41:1, step body 10.22:1.
     The scrim's stops in app.css are what hold those; re-measure if they move. --}}
@php
    use App\Enums\ArticleSection;

    $urls = app(App\Support\EntityUrl::class);

    // The steps are the four questions, not a second piece of copy. Three of
    // them, because the column sits beside a photograph and a fourth turns it
    // into a list. Each falls back to the article's own column text, and the
    // whole set falls back to the 30-second summary.
    $stepsFor = function ($article) {
        $steps = collect([ArticleSection::WhatHappened, ArticleSection::WhyItMatters, ArticleSection::Opportunity])
            ->map(fn ($s): array => ['label' => $s->label(), 'text' => $article->{$s->field()}])
            ->filter(fn (array $s): bool => filled($s['text']))
            ->values();

        if ($steps->isEmpty()) {
            $steps = collect($article->summary ?? [])->filter()->take(3)
                ->map(fn ($t): array => ['label' => null, 'text' => $t])->values();
        }

        return $steps;
    };

    $slides = $section['items']->values()->map(function ($article, $i) use ($urls, $stepsFor, $backdrop) {
        // The illustrative backdrop is page furniture for the lead slide only.
        // Every other slide shows its own article's photograph, which is what
        // keeps a generated image out of anything a reader reads as reportage.
        $image = $i === 0 && $backdrop ? $backdrop : $article->heroMedia;

        // The last phrase of the headline is set in mint. The prototype sets it
        // in italic too — Readex Pro has no italic and Arabic has no cursive
        // variant, so a browser would synthesise an oblique, which is a slanted
        // Arabic word rather than an emphasised one. Colour carries it alone.
        $words = preg_split('/\s+/', trim((string) $article->title));
        $emphasis = count($words) > 3 ? array_pop($words) : null;

        return [
            'article' => $article,
            'url' => $urls->for($article),
            'image' => $image,
            'alt' => $i === 0 && $backdrop
                ? (string) $backdrop->getCustomProperty('alt')
                : ($article->hero_alt ?? ''),
            'lead' => implode(' ', $words),
            'emphasis' => $emphasis,
            'steps' => $stepsFor($article),
        ];
    });

    $count = $slides->count();
@endphp

@if ($count)
    {{--
        The photograph is the section's background, not a column in it: one
        absolutely positioned layer per slide behind everything, with the scrim
        over it and the grid on top. Full bleed to the viewport edges and flush
        with the ticker above — no inset, no radius, no gap.
    --}}
    <section class="full-bleed relative isolate" aria-labelledby="big-story-heading"
             @if ($count > 1)
                 x-data="{ i: 0, n: {{ $count }}, go(d) { this.i = (this.i + d + this.n) % this.n } }"
                 role="group" aria-roledescription="عرض متتابع" aria-label="{{ $section['title'] }}"
             @endif>
        @foreach ($slides as $k => $slide)
            <div class="absolute inset-0 z-0"
                 @if ($count > 1) x-show="i === {{ $k }}" @if ($k > 0) style="display:none" @endif @endif>
                <x-ui.responsive-image
                    :media="$slide['image']"
                    :alt="$slide['alt']"
                    ratio="hero"
                    :fill="true"
                    :eager="$k === 0"
                    :sizes="$sizes"
                    {{-- The stage is 2.4:1 against a 1.775:1 source, so `cover`
                         crops the vertical hard. Holding it at 40% keeps the
                         tower complete from its top to its base, the sunset
                         behind it, and the lit streets along the bottom edge —
                         centred, the frame lost all three. --}}
                    focal="50% 40%"
                />
            </div>
        @endforeach

        <div class="hero-scrim absolute inset-0 z-0" aria-hidden="true"></div>
        <div class="hero-seam absolute inset-x-0 top-0 z-0 h-16" aria-hidden="true"></div>

        {{-- Everything readable sits on the page's own grid, so the headline
             lines up with the sections below it while the picture runs past
             them to the viewport edge. --}}
        {{-- 600px at desktop, and the number is an outcome rather than a
             choice. At 520 the stage was 2.77:1 against a 1.775:1 photograph and
             `cover` showed 64% of its height — a zoomed slice with the towers
             cut top and bottom. 600 shows 74%, which is what it takes for the
             tower to read whole. The constraint is the whole stack: topbar 49 + masthead 80
             + ticker 60 + big story must clear a 900px viewport with the next
             section showing. At 620 the stack came to 854 and left 46px of it —
             which is not "beginning to show", and on a real 900px window, once
             browser chrome is taken off, the button was below the fold. --}}
        <div class="relative z-10 mx-auto grid min-h-[34rem] w-full max-w-[1300px] lg:min-h-[37.5rem] lg:grid-cols-[1.36fr_0.64fr]">
            {{-- The label sits at the top of the column and the story at the
                 bottom, so the space between them is the section's height doing
                 the work rather than a margin. It is the same label on every
                 slide, so it lives outside the slide loop. --}}
            <div class="flex flex-col justify-between p-7 sm:p-11">
                <p class="font-display text-[0.75rem] font-semibold uppercase tracking-[0.2em] text-white">
                    {{ $section['title'] }}
                </p>

                <div>
                @foreach ($slides as $k => $slide)
                    <div class="text-cream" @if ($count > 1) x-show="i === {{ $k }}" @if ($k > 0) style="display:none" @endif @endif>
                        {{-- 68px over 14ch, swept against all 72 demo
                             headlines at the 620px stage. The narrower measure
                             is what produces the reference's four-line rhythm:
                             30 of the 72 now set four lines and none sets more,
                             where 18ch put almost all of them on three. 13ch
                             reads better still — 46 of 72 at four lines — but
                             one headline runs to five and spills past the
                             column's bottom edge, so 14ch is the widest rhythm
                             that is safe for every headline we have. None
                             leaves the column into the questions beside it. --}}
                        <{{ $k === 0 ? 'h1' : 'h2' }}
                            @if ($k === 0) id="big-story-heading" @endif
                            class="line-clamp-4 max-w-[14ch] font-display text-[2.1rem] font-semibold leading-[1.1] sm:text-[2.6rem] lg:text-[clamp(2rem,3.33vw,3rem)]">
                            <a href="{{ $slide['url'] }}" class="transition hover:text-cream/85">
                                {{ $slide['lead'] }}@if ($slide['emphasis']) <span class="text-mint">{{ $slide['emphasis'] }}</span>@endif
                            </a>
                        </{{ $k === 0 ? 'h1' : 'h2' }}>

                        @if ($slide['article']->subtitle)
                            <p class="mt-5 line-clamp-3 max-w-[44ch] text-[0.9375rem] leading-[1.75] text-cream/75">
                                {{ $slide['article']->subtitle }}
                            </p>
                        @endif

                        <p class="mt-5 flex flex-wrap items-center gap-x-3 text-xs text-cream/65">
                            @if ($slide['article']->author)<span>بقلم {{ $slide['article']->author->name }}</span>@endif
                            @if ($slide['article']->published_at)
                                <span aria-hidden="true">·</span>
                                <time datetime="{{ $slide['article']->published_at->toIso8601String() }}" class="ltr-isolate nums-tabular">
                                    {{ $slide['article']->published_at->format('Y-m-d') }}
                                </time>
                            @endif
                            <span aria-hidden="true">·</span>
                            <span class="nums-tabular">{{ $slide['article']->reading_time }} دقيقة قراءة</span>
                        </p>

                        <a href="{{ $slide['url'] }}"
                           class="mt-7 inline-flex w-fit items-center gap-2 rounded-full border border-white/70 px-6 py-3 text-sm font-medium text-white transition hover:border-white hover:bg-white/10">
                            اقرأ القصة كاملة
                            <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
                        </a>
                    </div>
                @endforeach
                </div>
            </div>

            {{-- The three questions, on the photograph. Each numeral carries its
                 own hairline rather than one rule running the column, so a step
                 with two lines and a step with four still read as equals. --}}
            <div class="relative flex flex-col justify-center p-7 text-cream sm:p-9">
                {{-- Local, and only as wide as this column: the frame is not
                     tinted to rescue three lines of text. --}}
                <span class="steps-scrim pointer-events-none absolute inset-0" aria-hidden="true"></span>
                @foreach ($slides as $k => $slide)
                    <ol class="relative space-y-[3.75rem]"
                        @if ($count > 1) x-show="i === {{ $k }}" @if ($k > 0) style="display:none" @endif @endif>
                        @foreach ($slide['steps'] as $step)
                            <li class="grid grid-cols-[auto_1fr] gap-4">
                                <span class="flex items-stretch gap-4" aria-hidden="true">
                                    <span class="nums-tabular font-sans text-[2.625rem] font-normal leading-none tracking-[0.02em] text-gold/85">
                                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <span class="w-px bg-white/25"></span>
                                </span>
                                <div class="min-w-0">
                                    @if ($step['label'])
                                        <p class="mb-2 font-display text-[0.75rem] font-semibold uppercase tracking-[0.13em] text-white">
                                            {{ $step['label'] }}
                                        </p>
                                    @endif
                                    {{-- Full cream, not cream/75: the step
                                         text sits at the far edge of the
                                         photograph where the scrim is doing
                                         the least work. --}}
                                    <p class="line-clamp-2 text-[0.84375rem] leading-[1.65] text-cream">
                                        {{ $step['text'] }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endforeach
            </div>

            @if ($count > 1)
                {{-- Real controls for a real carousel. The thumbnail is the
                     slide the next button goes to, so the affordance is not
                     decoration. --}}
                <div class="absolute bottom-7 end-7 z-10 flex items-center gap-3 sm:bottom-8 sm:end-8">
                    <button type="button" @click="go(-1)" aria-label="الشريحة السابقة"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-white/70 text-white transition hover:bg-white/10">
                        <span aria-hidden="true" class="inline-block rtl:-scale-x-100">←</span>
                    </button>

                    @foreach ($slides as $k => $slide)
                        {{-- relative, because the picture inside fills its box
                             absolutely and would otherwise resolve against the
                             section and cover the photograph. --}}
                        <span class="relative block h-11 w-16 overflow-hidden rounded-md border border-white/30"
                              x-show="i === {{ ($k - 1 + $count) % $count }}"
                              @if ((($k - 1 + $count) % $count) !== 0) style="display:none" @endif
                              aria-hidden="true">
                            <x-ui.responsive-image :media="$slide['image']" alt="" ratio="card"
                                                   scale="thumb" sizes="64px" class="h-full w-full" :fill="true" />
                        </span>
                    @endforeach

                    <button type="button" @click="go(1)" aria-label="الشريحة التالية"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-white/70 text-white transition hover:bg-white/10">
                        <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
                    </button>
                </div>
            @endif
        </div>
    </section>
@endif
