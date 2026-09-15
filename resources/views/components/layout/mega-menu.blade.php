@props(['menu' => null])
@php
    $items = $menu ?? collect();

    // One rule for every top-level link, mega or not. They sat on two different
    // treatments before — uppercase and letterspaced under a mega panel, plain
    // sentence-case beside it — which read as two navigations in one bar.
    // Full-bar height so the active 2px rule lands on the masthead's own edge.
    // whitespace-nowrap and shrink-0: as flex children these links were
    // shrinking below their text width and wrapping each Arabic label onto two
    // lines inside the bar.
    //
    // Size is bounded by the labels, not by taste: eight Arabic categories at
    // 13px come to 750px, which does not fit beside the wordmark at 1280. The
    // tracking is tight (0.03em rather than 0.07em) because that is what buys
    // the extra half-pixel of size at every width.
    $link = 'inline-flex h-[4.875rem] shrink-0 items-center gap-1 whitespace-nowrap border-b-2 font-display text-[0.75rem] min-[1400px]:text-[0.78125rem] font-semibold uppercase tracking-[0.03em] transition';
    $state = fn (bool $active): array => [
        'border-g-900 text-g-950' => $active,
        'border-transparent text-g-900 hover:text-g-600' => ! $active,
    ];
@endphp
<nav {{ $attributes->class(['items-center gap-[1rem] min-[1400px]:gap-[1.125rem]']) }} aria-label="التنقل الرئيسي">
    @foreach ($items as $item)
        @if ($item['is_mega'] && count($item['children']))
            {{-- Mega panels open on hover and focus, and close on Escape — a
                 keyboard user must be able to get out of them. --}}
            <div
                class="relative"
                x-data="{ open: false }"
                @mouseenter="open = true"
                @mouseleave="open = false"
                @focusin="open = true"
                @focusout="open = false"
                @keydown.escape.window="open = false"
            >
                <a href="{{ $item['url'] }}"
                   {{-- Uppercase, letterspaced, and the current section marked
                        by a 2px rule under it rather than by colour alone. --}}
                   @class([$link, ...$state($item['is_active'] ?? false)])
                   :aria-expanded="open.toString()">
                    {{ $item['label'] }}
                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                        <path d="M3 4.5L6 7.5l3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                    </svg>
                </a>

                <div x-show="open" x-cloak x-transition.opacity
                     class="absolute top-full start-0 z-50 mt-1 w-[540px] rounded-xl border border-line bg-white p-5 shadow-lg">
                    @php $groups = collect($item['children'])->groupBy(fn ($c) => $c['column_group'] ?? ''); @endphp
                    <div class="grid grid-cols-2 gap-6">
                        @foreach ($groups as $groupName => $children)
                            <div>
                                @if ($groupName !== '')
                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-3">{{ $groupName }}</div>
                                @endif
                                <ul class="space-y-1.5">
                                    @foreach ($children as $child)
                                        <li>
                                            <a href="{{ $child['url'] }}" class="block text-sm text-ink transition hover:text-g-700">
                                                {{ $child['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <a href="{{ $item['url'] }}" @class([$link, ...$state($item['is_active'] ?? false)])>
                {{ $item['label'] }}
            </a>
        @endif
    @endforeach
</nav>
