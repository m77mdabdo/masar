@props(['section', 'backdrop' => null, 'sizes' => '(max-width: 1024px) 100vw, 96vw'])
{{-- contrast-safe: every foreground sits on the photograph, under its own
     blurred local scrim, so the ground is the picture and not a token.
     Measured 2026-10-28 off the rendered pixels behind each element, text
     hidden, against the lightest pixel under its worst line box — all three
     slides, both lead-slide backdrops — at 375 / 430 / 768 / 1024 / 1280 /
     1440 / 1920: every element ≥ 4.90 (the mint word); numerals ≥ 4.93.
     The per-width tables are in CLAUDE.md §2. The opacities in app.css are
     what hold these; re-measure if they move or a backdrop is added. --}}
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
            // A backdrop carries its own focal point (its tower's position);
            // article photographs keep the stage's default.
            'focal' => $i === 0 && $backdrop
                ? (string) $backdrop->getCustomProperty('focal', '50% 40%')
                : '50% 40%',
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
        absolutely positioned layer per slide behind everything, and the grid
        on top. There is no full-frame scrim: each text block carries its own
        blurred one, so the photograph stays visible around them. Full bleed to the viewport edges and flush
        with the ticker above — no inset, no radius, no gap.
    --}}
    {{-- One slide: the headline names the section. Several: the group's own
         label does, because aria-labelledby keeps resolving to slide one's
         headline after the reader has moved to slide three. --}}
    {{-- `overflow-clip`, not `-hidden`: the scrims reach past the stage (427px
         of content in a 375px box on a photo slide), and a hidden overflow is
         still a scroll container — a focus move or a find-in-page can scroll
         the whole slide sideways. A clipped one cannot be scrolled at all. --}}
    <section class="full-bleed relative isolate overflow-clip" @if ($count === 1) aria-labelledby="big-story-heading" @endif
             @if ($count > 1)
                 {{-- Auto-advancing with a drift (Task 2.10): the logic and its
                      reasoning are resources/js/big-story.js, loaded after the
                      page's load event by the script below — `x-ignore` holds
                      the section until it arrives. Hover and keyboard focus hold it; leaving
                      releases it. --}}
                 x-data="bigStory({{ $count }})" data-carousel x-ignore x-bind="root"
                 role="group" aria-roledescription="عرض متتابع" aria-label="{{ $section['title'] }}"
             @endif>
        @foreach ($slides as $k => $slide)
            {{-- Stacked, and cross-faded rather than swapped: the incoming slide
                 fades in over the outgoing one, which stays opaque beneath it,
                 so the frame never dips. Only the current, outgoing and next
                 slides are in the tree, so only the next image is fetched. --}}
            <div @class(['hero-photo absolute inset-0 z-0', 'is-armed' => $count > 1 && $k === 0])
                 style="--focal: {{ $slide['focal'] ?? '50% 50%' }};@if ($count > 1 && $k > 0) display:none @endif"
                 @if ($count > 1) x-bind="photo({{ $k }})" @endif>
                {{-- `ratio` does not shape this box: with :fill the parent owns it,
                     and the ratio only sets the img's width/height attributes,
                     which the cover CSS overrides. The crop is `focal`'s. --}}
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
                    :focal="$slide['focal']"
                />
            </div>
        @endforeach

        <div class="hero-seam absolute inset-x-0 top-0 z-0 h-16" aria-hidden="true"></div>

        {{-- Everything readable sits on the page's own grid, so the headline
             lines up with the sections below it while the picture runs past
             them to the viewport edge. --}}
        {{-- The stage was a fixed 600px, chosen so the whole stack cleared a
             900px viewport with the next section showing. Superseded by the
             owner's height targets below, which trade that peek for a stage at
             the backdrops' own proportion. --}}
        {{-- Owner's targets (2026-10-28), taller than the backdrops' 2.28:1 so
             the picture takes more of the screen: 13.5rem + 35vw, never under
             36.25rem — 580 at 1024, ~660 at 1280, 720 at 1440 — and capped at
             53.75rem, 860 at 1920. `cover` crops the sides, not the sky. On a
             short screen it still gives way to the fold: never taller than
             the viewport less 7rem — the ~136px stack above it less the
             button's 44px inset, with ~20px to spare — so the button stays on
             screen. 32rem is the floor, where the questions column still
             fits. --}}
        <div class="relative z-10 mx-auto grid min-h-[34rem] w-full max-w-[1300px] lg:min-h-[clamp(32rem,min(max(36.25rem,calc(13.5rem_+_35vw)),calc(100svh_-_7rem)),53.75rem)] lg:grid-cols-[1.36fr_0.64fr]">
            {{-- The label sits at the top of the column and the story at the
                 bottom, so the space between them is the section's height doing
                 the work rather than a margin. It is the same label on every
                 slide, so it lives outside the slide loop. --}}
            {{-- gap-6: when the column is no taller than its content (below
                 lg) justify-between distributes nothing, and the label sat on
                 the headline with no space at all. --}}
            <div class="flex flex-col justify-between gap-4 p-7 sm:gap-6 sm:p-11">
                <p class="kicker-scrim relative isolate z-[1] w-fit font-display text-[0.75rem] font-semibold uppercase tracking-[0.2em] text-white">
                    {{ $section['title'] }}
                </p>

                {{-- Every slide sits in the same grid cell and is hidden with
                     visibility, not display: the cell is as tall as the
                     tallest slide, so turning the carousel never changes the
                     hero's height (it did below lg, 796 → 770px at 375), and
                     each slide is bottom-aligned so the button stays put. --}}
                {{-- Announces the reader's own navigation only: `live` is off
                     while the carousel advances by itself. --}}
                <div class="relative grid" @if ($count > 1) aria-live="off" x-bind="announcer" @endif>
                    {{-- The scrims sit at z-0 and every text block at z-[1]: they reach 5rem
                         past their own block, and painting in DOM order put
                         them over the kicker and the button. --}}
                    <span class="text-scrim pointer-events-none absolute z-0" aria-hidden="true"></span>
                @foreach ($slides as $k => $slide)
                    <div class="relative z-[1] self-end text-cream [grid-area:1/1]"
                         @if ($count > 1) :style="{ visibility: i === {{ $k }} ? 'visible' : 'hidden' }" @if ($k > 0) style="visibility:hidden" @endif @endif>
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
                            {{-- 14ch from lg only: it keeps the headline out of
                                 the questions column beside it, which below lg
                                 is under it. At 375 the cap clamped 1 of the 83
                                 demo headlines at five lines; without it, none.
                                 1.2, not 1.1: Readex Pro's Arabic marks reach past
                                 a 1.1 line box, and the clamp's overflow cut the
                                 dots under the last line and the tops of the
                                 first. The padding gives the ink room inside the
                                 clamp; the matching negative margin keeps the
                                 block where it was. --}}
                            class="-my-[0.15em] line-clamp-4 py-[0.15em] lg:max-w-[14ch] font-display text-[2.1rem] font-semibold leading-[1.2] sm:text-[2.6rem] lg:text-[clamp(2rem,3.33vw,3rem)]">
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
            {{-- The carousel controls sit over this column's bottom edge. Below
                 lg the column is last in the stack, and between lg and xl the
                 stage is short and the answers run to three lines; in both the
                 controls covered the third question. pb-24 keeps them clear.
                 It releases at xl, not lg, on purpose: at 1024 with 36px the
                 controls covered the third answer; from 1280 answers are two
                 lines and the tightest gap measured is 40px.
                 `sm:pb-24` duplicates `pb-24` and is load-bearing: `sm:p-9` is in
                 a later media query at equal specificity and would otherwise
                 reset the bottom to 36px from 640px up. Do not tidy it away. --}}
            <div @class(['relative flex flex-col justify-center p-7 pt-5 text-cream sm:p-9', 'pb-24 sm:pb-24 xl:pb-9' => $count > 1])>
                <div class="relative grid">
                    <span class="questions-scrim pointer-events-none absolute z-0" aria-hidden="true"></span>
                @foreach ($slides as $k => $slide)
                    {{-- 60px fills the side column's height at lg+; stacked
                         under the headline there is no slack to fill. --}}
                    <ol class="relative z-[1] space-y-10 self-center [grid-area:1/1] lg:space-y-[3.75rem]"
                        @if ($count > 1) :style="{ visibility: i === {{ $k }} ? 'visible' : 'hidden' }" @if ($k > 0) style="visibility:hidden" @endif @endif>
                        @foreach ($slide['steps'] as $step)
                            <li class="grid grid-cols-[auto_1fr] gap-4">
                                <span class="flex items-stretch gap-4" aria-hidden="true">
                                    <span class="nums-tabular font-sans text-[2.625rem] font-normal leading-none tracking-[0.02em] text-gold">
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
                                    {{-- Three lines between lg and xl: the column is
                                         328px at 1024 and two lines cut every
                                         answer mid-sentence. --}}
                                    <p class="line-clamp-3 text-[0.84375rem] leading-[1.65] text-cream xl:line-clamp-2">
                                        {{ $step['text'] }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endforeach
                </div>
            </div>

            @if ($count > 1)
                {{-- The circles are 36px (under the 44px touch minimum); a 5px
                     pseudo-element ring makes each hit area 46×46 without
                     changing what is drawn. Verified by clicking 21px off
                     centre on all four sides at 375, 768 and 1440. --}}
                {{-- Real controls for a real carousel. The thumbnail is the
                     slide the next button goes to, so the affordance is not
                     decoration. --}}
                <div class="absolute bottom-7 end-7 z-10 flex items-center gap-3 sm:bottom-8 sm:end-8">
                    <button type="button" @click="go(-1)" aria-label="الشريحة السابقة"
                            class="relative flex h-9 w-9 items-center justify-center rounded-full border border-white/70 text-white transition before:absolute before:-inset-[5px] before:content-[''] hover:bg-white/10">
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

                    <button type="button" @click="go(1)" aria-label="الشريحة التالية" data-next
                            class="relative flex h-9 w-9 items-center justify-center rounded-full border border-white/70 text-white transition before:absolute before:-inset-[5px] before:content-[''] hover:bg-white/10">
                        <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
                    </button>

                    {{-- The pause control (WCAG 2.2.2) is added by big-story.js,
                         after the load event, with this cluster's own button
                         class: before then nothing moves, so there is nothing
                         to stop, and its markup would sit in front of the LCP
                         image for nothing. --}}
                </div>
            @endif
        </div>
    </section>
    @if ($count > 1)
        {{-- After the photograph above has painted, so the carousel's bytes are
             never in front of the LCP paint. The load event alone was not late
             enough: measured, it fired at 243ms and the hero painted at 262ms,
             and the chunk was requested in between. Load, then the image's
             decode, then two frames, so at least one has been painted. --}}
        <script type="module">
            const load = () => (document.querySelector('[data-carousel] img')?.decode() ?? Promise.resolve()).catch(() => {})
                .then(() => requestAnimationFrame(() => requestAnimationFrame(() => import(@js(Vite::asset('resources/js/big-story.js'))))));
            document.readyState === 'complete' ? load() : addEventListener('load', load, { once: true });
        </script>
    @endif
@endif
