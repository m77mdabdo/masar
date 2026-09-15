@props(['points' => [], 'area' => false, 'height' => 44, 'width' => 120, 'stroke' => null])
@php
    use App\Support\Sparkline;

    $weight = (float) ($stroke ?? ($area ? 2 : 1.4));
    $line = Sparkline::path((array) $points, (float) $width, (float) $height, $weight);
@endphp

@if ($line !== '')
    {{-- Drawn without a value axis on purpose: these are editor-entered shape
         points, not a series we can stand behind to the pixel. The curve passes
         through every one of them — a smoothing that moved the points would be
         drawing a different series from the one an editor typed. --}}
    <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" aria-hidden="true"
         {{ $attributes->class(['block h-full w-full']) }}>
        @if ($area)
            <path d="{{ Sparkline::area((array) $points, (float) $width, (float) $height, $weight) }}"
                  fill="currentColor" opacity="0.12" />
        @endif
        <path d="{{ $line }}" fill="none" stroke="currentColor" stroke-width="{{ $weight }}"
              stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
    </svg>
@endif
