@props(['menu' => null, 'locale'])
@php $items = $menu ?? collect(); @endphp
<div x-data="{ open: false }" class="lg:hidden">
    <button type="button" @click="open = true" class="rounded-lg p-2 text-g-700 transition hover:bg-line/60"
            aria-label="فتح القائمة" :aria-expanded="open.toString()">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
    </button>

    <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-g-950/40" @click="open = false" aria-hidden="true"></div>

        {{-- Anchored to the inline-end edge, which is the left in RTL. --}}
        <div class="absolute inset-y-0 end-0 flex w-[85%] max-w-sm flex-col bg-cream p-5 shadow-xl"
             x-trap.noscroll="open"
             x-transition:enter="transition duration-200" role="dialog" aria-modal="true" aria-label="القائمة">
            <div class="mb-6 flex items-center justify-between">
                <span class="font-display text-xl font-semibold text-g-900">{{ setting('identity.site_name') }}</span>
                <button type="button" @click="open = false" class="rounded-lg p-2 text-g-700" aria-label="إغلاق">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto" aria-label="تنقل الجوال">
                <ul class="space-y-1">
                    @foreach ($items as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="block rounded-lg px-3 py-2.5 font-medium text-g-900 transition hover:bg-line/60">
                                {{ $item['label'] }}
                            </a>
                            @if (count($item['children']))
                                <ul class="ms-3 space-y-0.5 border-s border-line ps-3">
                                    @foreach ($item['children'] as $child)
                                        <li>
                                            <a href="{{ $child['url'] }}" class="block rounded px-2 py-1.5 text-sm text-ink-3 transition hover:text-g-700">
                                                {{ $child['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            <x-ui.button :href="route('web.newsletter', $locale)" class="mt-4 w-full">اشترك في النشرة</x-ui.button>
        </div>
    </div>
</div>
