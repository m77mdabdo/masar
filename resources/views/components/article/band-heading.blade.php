@props(['title', 'id' => null, 'icon' => null, 'href' => null, 'more' => null, 'nav' => false])
{{-- The small header row above a body band — key numbers, expert insight,
     related companies. Label on the start side, action on the end side. --}}
<div class="mb-4 mt-10 flex items-center gap-3 border-t border-line pt-4">
    @if ($icon)
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-line text-g-700" aria-hidden="true">
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}" /></svg>
        </span>
    @endif

    <h2 @if ($id) id="{{ $id }}" @endif class="font-display text-xs font-semibold uppercase tracking-[0.12em] text-g-900">
        {{ $title }}
    </h2>

    <span class="h-px flex-1"></span>

    @if ($href)
        <a href="{{ $href }}" class="shrink-0 text-xs text-ink-3 transition hover:text-g-700">
            {{ $more ?? 'الكل' }} <span aria-hidden="true" class="rtl:inline-block rtl:-scale-x-100">→</span>
        </a>
    @elseif ($nav)
        {{-- The prototype's prev/next pair. Rendered as real buttons with real
             labels: a bare arrow glyph is unreachable by keyboard and unnamed
             to a screen reader. --}}
        <div class="flex shrink-0 gap-1.5">
            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-full border border-line text-ink-3 transition hover:border-g-600 hover:text-g-700">
                <span class="sr-only">السابق</span>
                <svg class="h-3 w-3 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            </button>
            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-full border border-line text-ink-3 transition hover:border-g-600 hover:text-g-700">
                <span class="sr-only">التالي</span>
                <svg class="h-3 w-3 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
            </button>
        </div>
    @endif
</div>
