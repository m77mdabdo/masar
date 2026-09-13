@props(['label', 'tone' => 'light'])
{{-- The honest state, not an edge case: no editor has entered a figure, so the
     shape stays and the number does not get invented to fill it. --}}
<div @class([
    'flex min-h-[5rem] items-center justify-center rounded-lg border border-dashed px-4 py-5 text-center text-xs leading-relaxed',
    'border-cream/25 text-cream/60' => $tone === 'dark',
    'border-line text-ink-3' => $tone !== 'dark',
])>
    {{ $label }}
</div>
