@props(['tone' => 'neutral'])
@php
    $tones = [
        'neutral' => 'bg-ink/8 text-ink',
        'accent' => 'bg-mint/25 text-g-950',
        'gold' => 'bg-gold/20 text-ink',
        'positive' => 'bg-g-600/12 text-g-600',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium', $tones[$tone] ?? $tones['neutral']]) }}>
    {{ $slot }}
</span>
