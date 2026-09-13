@props(['menu' => null])
@php $items = $menu ?? collect(); @endphp
<nav {{ $attributes->class(['items-center gap-1']) }} aria-label="التنقل الرئيسي">
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
                   @class([
                       'inline-flex items-center gap-1 border-b-2 px-2.5 py-2 font-display text-[0.72rem] font-semibold uppercase tracking-[0.1em] transition',
                       'border-g-900 text-g-950' => $item['is_active'] ?? false,
                       'border-transparent text-g-900 hover:text-g-600' => ! ($item['is_active'] ?? false),
                   ])
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
            <a href="{{ $item['url'] }}" class="rounded-lg px-3 py-2 text-sm font-medium text-g-900 transition hover:bg-line/60">
                {{ $item['label'] }}
            </a>
        @endif
    @endforeach
</nav>
