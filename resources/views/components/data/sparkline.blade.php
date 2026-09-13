@props(['points' => [], 'area' => false, 'height' => 44, 'width' => 120])
@php
    $points = array_values(array_filter((array) $points, 'is_numeric'));
@endphp
@if (count($points) >= 2)
    @php
        $min = min($points);
        $max = max($points);
        $span = ($max - $min) ?: 1;
        $step = $width / (count($points) - 1);

        // Drawn without a value axis on purpose: these are editor-entered shape
        // points, not a series we can stand behind to the pixel.
        $coords = collect($points)
            ->map(fn (float $p, int $i): string => round($i * $step, 2).','.round($height - (($p - $min) / $span) * ($height - 4) - 2, 2))
            ->implode(' ');
    @endphp

    <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" aria-hidden="true"
         {{ $attributes->class(['block h-full w-full']) }}>
        @if ($area)
            <polygon points="0,{{ $height }} {{ $coords }} {{ $width }},{{ $height }}"
                     fill="currentColor" opacity="0.12" />
        @endif
        <polyline points="{{ $coords }}" fill="none" stroke="currentColor" stroke-width="{{ $area ? 2 : 1.4 }}"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
    </svg>
@endif
