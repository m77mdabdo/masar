@props(['menu' => null, 'locale'])
@php $items = $menu ?? collect(); @endphp
{{-- The drawer is the whole navigation; the bar shows eight of it. That is
     as true at 1440px as at 390px, so the burger is not a small-screen
     affordance here — it is the way to the rest of the menu. --}}
<div x-data="{ open: false }">
    {{-- The glyph stays 22px wide because that is the drawn mark; the *target*
         is padded out to clear the 24×24 minimum (WCAG 2.2, 2.5.8). It missed
         by two pixels, on the one control that reaches the rest of the
         navigation — and no exception applies: it is not inline text and there
         is no other route to the menu. --}}
    <button type="button" @click="open = true"
            class="flex items-center justify-center px-1 py-3 text-ink transition hover:opacity-70"
            aria-label="{{ __('site.nav.open_menu') }}" :aria-expanded="open.toString()">
        <span class="grid w-[1.375rem] gap-[5px]" aria-hidden="true">
            <span class="block h-[1.5px] w-full bg-current"></span>
            <span class="block h-[1.5px] w-full bg-current"></span>
            <span class="block h-[1.5px] w-full bg-current"></span>
        </span>
    </button>

    <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-g-950/40" @click="open = false" aria-hidden="true"></div>

        {{-- Anchored to the inline-end edge, which is the left in RTL. --}}
        <div class="absolute inset-y-0 end-0 flex w-[85%] max-w-sm flex-col bg-cream p-5 shadow-xl"
             x-trap.noscroll="open"
             x-transition:enter="transition duration-200" role="dialog" aria-modal="true" aria-label="{{ __('site.nav.menu') }}">
            <div class="mb-6 flex items-center justify-between">
                <span class="font-display text-xl font-semibold text-g-900">{{ setting('identity.site_name') }}</span>
                <button type="button" @click="open = false" class="rounded-lg p-2 text-g-700" aria-label="{{ __('site.nav.close') }}">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto" aria-label="{{ __('site.nav.mobile') }}">
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

            <x-ui.button :href="route('web.newsletter', $locale)" class="mt-4 w-full">{{ __('site.newsletter.subscribe_cta') }}</x-ui.button>
        </div>
    </div>
</div>
