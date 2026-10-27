@props(['href' => null, 'color' => null])
@php $style = $color ? "background-color: {$color}1a; color: {$color};" : null; @endphp
<{{ $href ? 'a' : 'span' }}
    @if ($href) href="{{ $href }}" @endif
    @if ($style) style="{{ $style }}" @endif
    {{ $attributes->class([
        // min-h-6 rather than more padding: a tag is a standalone link, so
        // WCAG 2.2's inline exception does not cover it, and growing the box
        // vertically keeps the chip's drawn shape.
        'inline-flex min-h-6 items-center rounded px-2 py-0.5 text-xs font-medium',
        'bg-g-900/8 text-g-900' => ! $color,
        'transition hover:opacity-80' => (bool) $href,
    ]) }}
>{{ $slot }}</{{ $href ? 'a' : 'span' }}>
