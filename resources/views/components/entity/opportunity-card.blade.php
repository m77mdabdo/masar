@props(['opportunity'])
@php
    $potential = $opportunity->potential;
    // The enum returns a brand token name; the template maps it to a class once.
    $tone = ['mint' => 'accent', 'gold' => 'gold', 'ink-3' => 'neutral'][$potential?->colour()] ?? 'neutral';
@endphp
<article class="flex flex-col rounded-xl border border-line bg-white p-5 transition hover:border-g-600">
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <x-ui.badge :tone="$tone">{{ $potential?->label() }}</x-ui.badge>
        @if ($opportunity->industry?->name)
            <x-ui.tag>{{ $opportunity->industry->name }}</x-ui.tag>
        @endif
    </div>

    <h3 class="font-display text-lg font-semibold leading-snug text-g-950">
        <a href="{{ app(App\Support\EntityUrl::class)->for($opportunity) }}" class="transition hover:text-g-700">
            {{ $opportunity->title }}
        </a>
    </h3>

    <p class="mt-2 flex-1 text-sm leading-relaxed text-ink-3">{{ Str::limit($opportunity->summary, 140) }}</p>

    @if ($opportunity->deadline)
        <div @class([
            'mt-4 flex items-center gap-1.5 text-xs',
            'text-gold-ink' => ! $opportunity->deadline->isPast(),
            'text-ink-3' => $opportunity->deadline->isPast(),
        ])>
            <span>آخر موعد</span>
            <time datetime="{{ $opportunity->deadline->toDateString() }}" class="ltr-isolate nums-tabular">
                {{ $opportunity->deadline->format('Y-m-d') }}
            </time>
        </div>
    @endif
</article>
