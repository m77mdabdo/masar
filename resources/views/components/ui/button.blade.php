@props(['variant' => 'primary', 'href' => null, 'size' => 'md'])
@php
    // The mint accent is never a button fill — CLAUDE.md §2.
    $classes = [
        'primary' => 'bg-g-900 text-cream hover:bg-g-700',
        'secondary' => 'bg-transparent text-g-900 border border-line hover:border-g-600',
        'ghost' => 'bg-transparent text-g-700 hover:text-g-900',
    ][$variant] ?? '';

    $sizing = ['sm' => 'px-3 py-1.5 text-sm', 'md' => 'px-5 py-2.5 text-sm', 'lg' => 'px-6 py-3 text-base'][$size];
@endphp
<{{ $href ? 'a' : 'button' }}
    @if ($href) href="{{ $href }}" @else type="{{ $attributes->get('type', 'button') }}" @endif
    {{ $attributes->class(['inline-flex items-center justify-center gap-2 rounded-lg font-medium transition', $classes, $sizing]) }}
>{{ $slot }}</{{ $href ? 'a' : 'button' }}>
