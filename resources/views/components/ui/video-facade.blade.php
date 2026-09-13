@props(['url', 'title' => '', 'poster' => null])
@php
    use App\Support\MediaConversions;

    // Parse the id locally; never call YouTube to find out what it is.
    preg_match('#(?:youtu\.be/|v=|embed/|shorts/)([A-Za-z0-9_-]{6,})#', (string) $url, $m);
    $id = $m[1] ?? null;

    // The poster is ours. i.ytimg.com sets no cookies, but it is still a
    // request to Google before the reader has asked for anything, and the rule
    // is no third-party request before a click — not no third-party cookie.
    // Without a local poster the facade shows its own dark plate instead.
    $posterUrl = $poster instanceof \Spatie\MediaLibrary\MediaCollections\Models\Media
        ? MediaConversions::fallbackSrc($poster)
        : $poster;
@endphp

@if ($id)
    <div
        x-data="{ loaded: false }"
        {{ $attributes->class(['relative overflow-hidden rounded-xl bg-g-950']) }}
        style="aspect-ratio: 16 / 9;"
    >
        <template x-if="! loaded">
            <button
                type="button"
                @click="loaded = true"
                class="group absolute inset-0 flex h-full w-full items-center justify-center"
                aria-label="تشغيل: {{ $title }}"
            >
                @if ($posterUrl)
                    <img
                        src="{{ $posterUrl }}" alt="" width="1600" height="900" loading="lazy" decoding="async"
                        class="absolute inset-0 h-full w-full object-cover opacity-80 transition group-hover:opacity-95"
                    />
                @endif

                <span class="relative flex h-16 w-16 items-center justify-center rounded-full bg-cream/95 text-g-900 shadow-lg transition group-hover:scale-105">
                    <svg class="h-6 w-6 ms-1 rtl:ms-0 rtl:me-1 rtl:-scale-x-100" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </span>
            </button>
        </template>

        {{-- Built only on click: nothing from youtube.com is requested on load. --}}
        <template x-if="loaded">
            <iframe
                :src="'https://www.youtube-nocookie.com/embed/{{ $id }}?autoplay=1&rel=0'"
                title="{{ $title }}"
                class="absolute inset-0 h-full w-full"
                allow="accelerometer; autoplay; encrypted-media; picture-in-picture"
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen
                loading="lazy"
            ></iframe>
        </template>
    </div>
@endif
