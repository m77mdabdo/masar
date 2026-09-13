@props(['section', 'sizes' => '(max-width: 1024px) 100vw, 62vw'])
{{-- contrast-safe: the headline sits on the hero scrim and the steps on a solid
     g-900 panel. Mint is 2.11:1 on cream and 7.78:1 on g-950. --}}
@php
    use App\Enums\ArticleSection;

    $article = $section['items']->first();
    $url = app(App\Support\EntityUrl::class)->for($article);

    // The steps are the four questions, not a second piece of copy. Three of
    // them, because the panel is a column beside a photograph and a fourth turns
    // it into a list. Each falls back to the article's own column text.
    $steps = collect([ArticleSection::WhatHappened, ArticleSection::WhyItMatters, ArticleSection::Opportunity])
        ->map(fn ($s): array => ['label' => $s->label(), 'text' => $article->{$s->field()}])
        ->filter(fn (array $s): bool => filled($s['text']))
        ->values();

    // Nothing written into the four columns: fall back to the 30-second summary
    // rather than render an empty panel.
    if ($steps->isEmpty()) {
        $steps = collect($article->summary ?? [])->filter()->take(3)
            ->map(fn ($t): array => ['label' => null, 'text' => $t])->values();
    }
@endphp

<section class="mb-6 mt-6" aria-labelledby="big-story-heading">
    <div class="grid overflow-hidden rounded-2xl lg:grid-cols-[1.36fr_0.64fr]">
        {{-- The headline is on the photograph, so the scrim is load-bearing:
             cream text over an unknown crop is only legible because the gradient
             guarantees a dark ground under it. --}}
        <div class="relative isolate flex min-h-[24rem] flex-col justify-end bg-g-950 p-6 text-cream sm:p-10 lg:min-h-[29rem]">
            <x-ui.responsive-image
                :media="$article->heroMedia"
                :alt="$article->hero_alt ?? ''"
                ratio="hero"
                :fill="true"
                :eager="true"
                :sizes="$sizes"
                class="-z-10"
            />
            <div class="hero-scrim absolute inset-0 -z-10" aria-hidden="true"></div>

            <span class="mb-4 inline-flex w-fit items-center rounded-full border border-cream/35 px-3 py-1 text-xs font-medium">
                {{ $section['title'] }}
            </span>

            {{-- The largest type on the page. If the big story does not read as
                 bigger than a lead, the page has no hierarchy. --}}
            @php
                // The prototype sets the last phrase of the headline in italic
                // mint. Readex Pro has no italic and Arabic has no cursive
                // variant — a browser would synthesise an oblique, which is a
                // slanted Arabic word, not an emphasised one. The colour carries
                // the emphasis on its own; mint is 7.78:1 on this ground.
                $words = preg_split('/\s+/', trim((string) $article->title));
                $emphasis = count($words) > 3 ? array_pop($words) : null;
                $lead = implode(' ', $words);
            @endphp

            <h1 id="big-story-heading" class="max-w-[14ch] font-display text-[2.1rem] font-semibold leading-[1.08] sm:text-[2.9rem] lg:text-[3.25rem]">
                <a href="{{ $url }}" class="transition hover:text-cream/85">
                    {{ $lead }}@if ($emphasis) <span class="text-mint">{{ $emphasis }}</span>@endif
                </a>
            </h1>

            @if ($article->subtitle)
                <p class="mt-4 max-w-[46ch] leading-relaxed text-cream/80">{{ $article->subtitle }}</p>
            @endif

            <p class="mt-5 flex flex-wrap items-center gap-x-3 text-xs text-cream/65">
                @if ($article->author)<span>بقلم {{ $article->author->name }}</span>@endif
                @if ($article->published_at)
                    <span aria-hidden="true">·</span>
                    <time datetime="{{ $article->published_at->toIso8601String() }}" class="ltr-isolate nums-tabular">
                        {{ $article->published_at->format('Y-m-d') }}
                    </time>
                @endif
                <span aria-hidden="true">·</span>
                <span class="nums-tabular">{{ $article->reading_time }} دقيقة قراءة</span>
            </p>

            <a href="{{ $url }}"
               class="mt-6 inline-flex w-fit items-center gap-2 rounded-full bg-cream px-6 py-3 text-sm font-medium text-g-900 transition hover:bg-mint">
                اقرأ القصة كاملة
                <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
            </a>
        </div>

        {{-- A solid panel, not a tint over the photograph: the steps are a
             reading path and need a ground that does not change with the crop. --}}
        <div class="flex flex-col justify-center gap-6 bg-g-900 p-6 text-cream sm:p-8">
            <ol class="space-y-6">
                @foreach ($steps as $step)
                    <li class="grid grid-cols-[2.25rem_1fr] gap-3">
                        <span class="nums-tabular font-display text-2xl font-semibold leading-none text-mint" aria-hidden="true">
                            {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <div class="min-w-0">
                            @if ($step['label'])
                                <p class="mb-1 font-display text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-mint">
                                    {{ $step['label'] }}
                                </p>
                            @endif
                            <p class="text-sm leading-relaxed text-cream/80">{{ Str::limit($step['text'], 130) }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
