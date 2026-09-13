@props(['value', 'change' => null, 'size' => 'md'])
@php
    // <bdi>, not a forced direction: a value is sometimes a bare figure
    // ("11,234.56") and sometimes a figure inside Arabic ("31 مليار ريال"), and
    // a hard direction:ltr reverses the second kind.
    $sizes = ['sm' => 'text-sm', 'md' => 'text-lg', 'lg' => 'text-2xl'][$size];
@endphp
<span {{ $attributes->class(['inline-flex items-baseline gap-2']) }}>
    <bdi class="nums-tabular font-display font-semibold {{ $sizes }}">{{ $value }}</bdi>

    @if ($change !== null)
        {{-- A direction glyph as well as a colour: colour alone is not a way to
             convey information (WCAG 1.4.1), and red/green is the pairing most
             readers cannot tell apart. --}}
        <span @class([
            'nums-tabular inline-flex items-center gap-0.5 text-xs font-medium',
            'text-g-600' => $change >= 0,
            'text-gold-ink' => $change < 0,
        ])>
            <span aria-hidden="true">{{ $change >= 0 ? '▲' : '▼' }}</span>
            <bdi>{{ number_format(abs($change), 2) }}%</bdi>
            <span class="sr-only">{{ $change >= 0 ? 'ارتفاع' : 'انخفاض' }}</span>
        </span>
    @endif
</span>
