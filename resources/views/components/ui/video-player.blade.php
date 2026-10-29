@props([
    'media' => null,
    'poster' => null,
    'title' => '',
    'description' => null,
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

    /*
     * A moving picture with no text alternative is inaccessible to a reader who
     * cannot see it, and a silent one is indistinguishable from a broken one to
     * a reader who cannot hear.
     *
     * `description` answers WCAG 1.2.1 for video-only prerecorded media. It
     * comes from the caller, or from the clip's own alt, or from the poster's —
     * the poster shows the same footage, so its description is the right
     * fallback. Never the filename: that fails the same rule for video as
     * CLAUDE.md §5 already states for images.
     */
    // instanceof on both, not just the poster: callers hand this component
    // anything with a getUrl(), and a null-safe call still fatals on an object
    // that simply has no such method.
    $isMedia = fn ($m): bool => $m instanceof \Spatie\MediaLibrary\MediaCollections\Models\Media;

    $clipAlt = $isMedia($media) ? \App\Support\MediaText::alt($media) : null;
    $posterAlt = $isMedia($poster) ? \App\Support\MediaText::alt($poster) : null;

    $describe = filled($description) ? $description : (filled($clipAlt) ? $clipAlt : $posterAlt);

    /*
     * Measured at seed time with ffprobe, not assumed. TRUE means the clip has
     * an audio track and owes captions; FALSE means it is silent and we say so;
     * NULL means nobody measured, and the player claims nothing either way —
     * a page may not describe what the code has not established (§5).
     */
    $hasAudio = $isMedia($media) ? $media->getCustomProperty('has_audio') : null;

    $describedBy = ($describe || $hasAudio === false) ? 'vid-desc-'.($media?->getKey() ?? uniqid()) : null;
@endphp

@if ($media)
    <figure {{ $attributes->class('m-0') }}>
        <div class="{{ 'overflow-hidden rounded-xl bg-g-950 '.$box->class() }}">
            {{--
                preload="none" is the whole point: until the reader presses play
                the page has paid for one poster image. playsinline keeps iOS
                from taking over the screen. Autoplay, where a caller asks for
                it, is always muted — a page that makes noise on load is a bug
                regardless of what the design says.
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
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                class="h-full w-full object-cover"
            >
                <source src="{{ $media->getUrl() }}" type="{{ $media->mime_type ?? 'video/mp4' }}" />
                <p class="p-4 text-sm text-cream">
                    {{ __('site.video.unsupported') }}
                    <a href="{{ $media->getUrl() }}" class="underline">{{ __('site.video.download') }}</a>
                </p>
            </video>
        </div>

        @if ($describedBy)
            {{-- Visible, not hidden text for screen readers only. "This clip has
                 no sound" is information a sighted reader wants too, and a
                 description of the footage is a caption in the ordinary sense. --}}
            <figcaption id="{{ $describedBy }}" class="mt-2 text-xs leading-6 text-ink-3">
                @if ($hasAudio === false)
                    <span class="font-medium">{{ __('site.video.silent') }}</span>
                @endif
                {{ $describe }}
            </figcaption>
        @endif
    </figure>
@endif
