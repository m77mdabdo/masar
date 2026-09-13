@props(['title', 'href' => null, 'more' => null, 'id' => null, 'tight' => false])
{{-- Label, hairline, action. The line is an <i> in the prototype; here it is a
     flex spacer with a border so it cannot be read as content by a screen
     reader. The heading id is a prop, not an attribute: aria-labelledby needs it
     on the <h2>, and $attributes would put it on the wrapper. --}}
<div @class(['flex items-center gap-4', 'mb-5 mt-13' => ! $tight, 'mb-4' => $tight])>
    <h2 @if ($id) id="{{ $id }}" @endif
        @class([
            'shrink-0 whitespace-nowrap font-display font-bold uppercase tracking-[0.14em] text-g-950',
            'text-[0.8rem]' => ! $tight,
            'text-[0.75rem]' => $tight,
        ])>{{ $title }}</h2>

    <span class="h-px flex-1 bg-line" aria-hidden="true"></span>

    @if ($href)
        <a href="{{ $href }}" class="shrink-0 whitespace-nowrap text-xs text-ink-3 transition hover:text-g-700">
            {{ $more ?? 'الكل' }}
            <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
        </a>
    @endif
</div>
