@props(['article', 'sections' => [], 'extras' => []])
@php
    $entries = collect($sections)
        ->map(fn ($s): array => ['href' => '#s-'.$s->value, 'label' => $s->label()])
        ->concat($extras)
        ->values();
@endphp

@if ($entries->isNotEmpty())
    {{--
        Scroll-spy in eight lines of Alpine rather than a library: an
        IntersectionObserver marks the section currently crossing the top third
        of the viewport, and the active entry gets a bar on the *start* edge so
        it mirrors with the text.

        With JavaScript off this is still a working list of anchors, which is
        the whole reason it is a <nav> of links and not a scripted widget.
    --}}
    <nav
        aria-labelledby="toc-heading"
        x-data="{
            active: null,
            init() {
                const targets = [...this.$el.querySelectorAll('a')]
                    .map(a => document.querySelector(a.getAttribute('href')))
                    .filter(Boolean);

                if (! targets.length || ! window.IntersectionObserver) return;

                const observer = new IntersectionObserver(
                    entries => entries.forEach(e => { if (e.isIntersecting) this.active = e.target.id }),
                    { rootMargin: '-10% 0px -70% 0px' }
                );

                targets.forEach(t => observer.observe(t));
            },
        }"
    >
        <h2 id="toc-heading" class="mb-3 font-display text-xs font-semibold uppercase tracking-[0.12em] text-ink-3">
            في هذه المادة
        </h2>
        <ul class="space-y-1 text-sm">
            @foreach ($entries as $entry)
                @php $anchor = ltrim($entry['href'], '#'); @endphp
                <li>
                    <a href="{{ $entry['href'] }}"
                       :aria-current="active === @js($anchor) ? 'true' : null"
                       :class="active === @js($anchor)
                           ? 'border-g-600 text-g-900 font-medium'
                           : 'border-line text-ink-3 hover:text-g-700'"
                       class="block border-s-2 py-1 ps-3 transition">
                        {{ $entry['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
