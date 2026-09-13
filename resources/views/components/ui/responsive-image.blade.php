@props([
    'media' => null,
    'src' => null,
    'alt' => '',
    'ratio' => 'card',
    'fill' => false,
    'scale' => 'full',
    'eager' => false,
    'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw',
])
@php
    use App\Support\MediaConversions;

    // The box is reserved by an aspect-ratio class, never by a width/height
    // pair guessed at the call site. A guessed pair that disagrees with the
    // real crop is the layout shift it was meant to prevent.
    // `fill` means the parent already owns the box — a hero whose height comes
    // from its content, a card with a scrim over it. The ratio class would fight
    // that: it sets position:relative and an aspect-ratio, so an absolutely
    // positioned copy collapses to zero height and the browser paints the alt
    // text instead of the photograph.
    $box = \App\Support\AspectRatio::from($ratio);
    $boxClass = $fill ? 'absolute inset-0 h-full w-full overflow-hidden' : $box->class();

    // `scale` narrows the candidate list. A 74px thumbnail offering an 1800px
    // file is bytes on the wire if a dense screen takes it, and markup on every
    // card whether or not it does.
    $webpSet = $media ? MediaConversions::srcset($media, webp: true, scale: $scale) : '';
    $jpegSet = $media ? MediaConversions::srcset($media, webp: false, scale: $scale) : '';
    $imgSrc = $media ? MediaConversions::fallbackSrc($media) : $src;
@endphp

@if ($imgSrc)
    <picture {{ $attributes->class(['block', $boxClass]) }}>
        @if ($webpSet)
            <source type="image/webp" srcset="{{ $webpSet }}" sizes="{{ $sizes }}" />
        @endif
        @if ($jpegSet)
            <source type="image/jpeg" srcset="{{ $jpegSet }}" sizes="{{ $sizes }}" />
        @endif

        {{-- Explicit width/height as well as the ratio class: the attributes are
             what a browser uses before CSS arrives. --}}
        <img
            src="{{ $imgSrc }}"
            alt="{{ $alt }}"
            width="{{ $box->width }}"
            height="{{ $box->height }}"
            @class(['h-full w-full object-cover', 'absolute inset-0' => $fill])
            @if ($eager) fetchpriority="high" decoding="async"
            @else loading="lazy" decoding="async" @endif
        />
    </picture>
@else
    {{-- A missing image still reserves the right box, so an unillustrated card
         never shifts the ones below it. --}}
    <div
        {{ $attributes->class(['flex items-center justify-center bg-line text-xs text-ink-3', $boxClass]) }}
        role="img"
        aria-label="{{ $alt ?: 'صورة غير متوفرة' }}"
    ></div>
@endif
