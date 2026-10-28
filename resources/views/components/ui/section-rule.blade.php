@props(['title', 'href' => null, 'more' => null, 'id' => null, 'tight' => false])
{{-- Label, hairline, action. The line is an <i> in the prototype; here it is a
     flex spacer with a border so it cannot be read as content by a screen
     reader. The heading id is a prop, not an attribute: aria-labelledby needs it
     on the <h2>, and $attributes would put it on the wrapper. --}}
<div @class(['flex items-center gap-4', 'mb-5 mt-13' => ! $tight, 'mb-4' => $tight])>
    <h2 @if ($id) id="{{ $id }}" @endif
        @class([
            // Wraps rather than overflowing: a French or English label is far
            // longer than the Arabic one, and nowrap pushed the action link
            // off a 320px screen (measured, i18n audit). One line still when
            // it fits; the rule takes whatever width is left.
            'min-w-0 text-balance font-display font-bold uppercase tracking-[0.14em] text-g-950',
            'text-[0.8rem]' => ! $tight,
            'text-[0.75rem]' => $tight,
        ])>{{ $title }}</h2>

    <span class="h-px flex-1 bg-line" aria-hidden="true"></span>

    @if ($href)
        {{-- inline-flex + min-h-6 rather than extra padding: the target reaches
             24px (WCAG 2.2, 2.5.8) while the text stays on the rule's centre
             line, so nothing moves. This link is not inline in a sentence, so
             the inline exception does not cover it. --}}
        <a href="{{ $href }}" class="inline-flex min-h-6 shrink-0 items-center whitespace-nowrap text-xs text-ink-3 transition hover:text-g-700">
            {{ $more ?? __('site.ui.all') }}
            <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
        </a>
    @endif
</div>
