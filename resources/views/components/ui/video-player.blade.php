@props([
    'media' => null,
    'poster' => null,
    'title' => '',
    'ratio' => 'hero',
    'autoplay' => false,
])
@php
    use App\Support\MediaConversions;

    $box = \App\Support\AspectRatio::from($ratio);

    // The poster is a generated conversion, never a frame grabbed at runtime:
    // a runtime grab means decoding the video to paint the placeholder, which
    // is the download preload="none" exists to avoid.
    $posterUrl = $poster instanceof \Spatie\MediaLibrary\MediaCollections\Models\Media
        ? MediaConversions::fallbackSrc($poster)
        : $poster;
@endphp

@if ($media)
    <div {{ $attributes->class(['overflow-hidden rounded-xl bg-g-950', $box->class()]) }}>
        {{--
            preload="none" is the whole point: until the reader presses play the
            page has paid for one poster image. playsinline keeps iOS from
            taking over the screen. Autoplay, where a caller asks for it, is
            always muted — a page that makes noise on load is a bug regardless
            of what the design says.
        --}}
        <video
            controls
            preload="none"
            playsinline
            width="{{ $box->width }}"
            height="{{ $box->height }}"
            @if ($posterUrl) poster="{{ $posterUrl }}" @endif
            @if ($autoplay) autoplay muted loop @endif
            @if ($title) aria-label="{{ $title }}" @endif
            class="h-full w-full object-cover"
        >
            <source src="{{ $media->getUrl() }}" type="{{ $media->mime_type ?? 'video/mp4' }}" />
            <p class="p-4 text-sm text-cream">
                متصفحك لا يدعم تشغيل هذا المقطع.
                <a href="{{ $media->getUrl() }}" class="underline">تنزيل الملف</a>
            </p>
        </video>
    </div>
@endif
