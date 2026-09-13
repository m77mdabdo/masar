@props(['section', 'blocks', 'article' => null, 'lead' => null])
@php
    /** @var \App\Enums\ArticleSection $section */
    // A heading block leading the section is its subhead, not a heading inside
    // it. That way an editor writes one thing and the page gets both the anchor
    // text and the h3 — rather than a kicker and a heading that repeat.
    $first = $blocks->first();
    $subhead = $first?->type === 'heading' ? ($first->data['text'] ?? null) : null;
    $rest = $subhead !== null ? $blocks->skip(1) : $blocks;
@endphp

<section id="s-{{ $section->value }}" class="mb-10 scroll-mt-24" aria-labelledby="s-{{ $section->value }}-heading">
    <div class="mb-4 flex items-start gap-4">
        <span @class([
            'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
            'bg-mint/25 text-g-950' => $section->tone() === 'mint',
            'bg-line text-g-700' => $section->tone() === 'line',
            'bg-gold/25 text-g-950' => $section->tone() === 'gold',
        ]) aria-hidden="true">
            {{-- rtl:-scale-x-100 so a directional glyph points the way the
                 reader is going, not the way the prototype's English did. --}}
            <svg class="h-4 w-4 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="{{ $section->iconPath() }}" />
            </svg>
        </span>

        <div class="min-w-0">
            {{-- The kicker is the question, the h2 is the answer. Where an
                 editor wrote no answer the question becomes the heading itself
                 — rendering both would read the same words twice to a screen
                 reader for the sake of a visual rhythm. --}}
            @if ($subhead !== null)
                <p class="font-display text-xs font-semibold uppercase tracking-[0.12em] text-ink-3">
                    {{ $section->label() }}
                </p>
                <h2 id="s-{{ $section->value }}-heading" class="mt-1 font-display text-xl font-semibold leading-snug text-g-950">
                    {{ $subhead }}
                </h2>
            @else
                <h2 id="s-{{ $section->value }}-heading" class="font-display text-xs font-semibold uppercase tracking-[0.12em] text-ink-3">
                    {{ $section->label() }}
                </h2>
            @endif
        </div>
    </div>

    <div class="prose-masar">
        {{-- The column first, then the blocks. The column is the answer an
             editor is required to give; the blocks are where they show it. --}}
        @if (filled($lead))
            <p>{{ $lead }}</p>
        @endif

        <x-article.blocks :blocks="$rest" :article="$article" />
    </div>
</section>
