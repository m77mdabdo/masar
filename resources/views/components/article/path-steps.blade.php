@props(['article'])
@php
    // Information → Understanding → Opportunity, rendered only where the editor
    // actually filled the step. A heading over an empty box is worse than no box.
    //
    // The icons are inline SVG, not an icon font: four glyphs do not justify a
    // font file on the critical path, and an icon font that fails to load leaves
    // a box where a meaning was.
    $steps = array_filter([
        ['label' => 'ما الذي حدث؟', 'body' => $article->what_happened, 'tone' => 'line', 'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'لماذا يهم هذا؟', 'body' => $article->why_it_matters, 'tone' => 'mint', 'icon' => 'M9 18h6M10 22h4M12 2a7 7 0 00-4 12.7V17h8v-2.3A7 7 0 0012 2z'],
        ['label' => 'من المتأثر؟', 'body' => $article->business_impact, 'tone' => 'line', 'icon' => 'M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['label' => 'أين الفرصة؟', 'body' => $article->opportunity, 'tone' => 'gold', 'icon' => 'M13 7l5 5m0 0l-5 5m5-5H6'],
    ], fn (array $step): bool => filled($step['body']));
@endphp

@if ($steps !== [])
    <div class="not-prose my-10 space-y-4">
        @foreach ($steps as $step)
            <section @class([
                'flex gap-4 rounded-xl border-s-4 bg-white p-5 sm:p-6',
                'border-s-mint' => $step['tone'] === 'mint',
                'border-s-line' => $step['tone'] === 'line',
                'border-s-gold' => $step['tone'] === 'gold',
            ])>
                <span @class([
                    'mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full',
                    'bg-mint/25 text-g-950' => $step['tone'] === 'mint',
                    'bg-line text-g-700' => $step['tone'] === 'line',
                    'bg-gold/25 text-g-950' => $step['tone'] === 'gold',
                ]) aria-hidden="true">
                    <svg class="h-4 w-4 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $step['icon'] }}" />
                    </svg>
                </span>

                <div class="min-w-0">
                    <h2 class="mb-2 font-display text-lg font-semibold text-g-900">{{ $step['label'] }}</h2>
                    <p class="text-base leading-relaxed text-ink">{{ $step['body'] }}</p>
                </div>
            </section>
        @endforeach
    </div>
@endif
