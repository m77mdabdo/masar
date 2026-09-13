@props(['value', 'label', 'change' => null])
<div class="rounded-xl border border-line bg-white p-4">
    {{-- <bdi>, not `ltr-isolate`: an editor-entered value is sometimes a bare
         figure and sometimes a figure inside Arabic, and a hard direction
         reverses the second kind. --}}
    <div class="font-display text-2xl font-semibold text-g-900"><bdi class="nums-tabular">{{ $value }}</bdi></div>
    <div class="mt-1 text-xs text-ink-3">{{ $label }}</div>
    @if ($change !== null)
        <div @class(['mt-1 ltr-isolate nums-tabular text-xs', 'text-g-600' => $change >= 0, 'text-gold-ink' => $change < 0])>
            {{ $change >= 0 ? '+' : '' }}{{ $change }}%
        </div>
    @endif
</div>
