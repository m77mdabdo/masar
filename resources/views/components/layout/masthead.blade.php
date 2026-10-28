@props(['locale', 'menu' => null])
@php $home = route('web.home', $locale); @endphp
{{-- 54px at rest, 50px once the page scrolls. The lost 4px goes to a
     margin, so the box in flow never changes height and nothing below it
     jumps when the bar tightens. The margin animates with the same duration
     and curve as the bar's height, so the two sum to 54px on every frame:
     an instant margin against a 200ms height dropped the page 4px for the
     length of the transition. --}}
<header class="sticky top-0 z-40 border-b border-line bg-cream/95 backdrop-blur transition-[margin] duration-200 motion-reduce:transition-none"
        x-data="{ stuck: false }" x-init="stuck = scrollY > 30"
        @scroll.window.passive="stuck = scrollY > 30" :class="stuck && 'mb-1'">
    <x-ui.container>
        {{-- Owner's target (2026-10-28): the wordmark keeps its size; the bar
             gives up vertical padding, and the height goes to the hero. --}}
        <div class="flex h-[3.375rem] items-center gap-[1.375rem] transition-[height] duration-200 motion-reduce:transition-none"
             :class="stuck ? '!h-[3.125rem]' : ''">
            <a href="{{ $home }}" class="flex shrink-0 items-baseline gap-2.5" aria-label="{{ setting('identity.site_name') }}">
                {{-- The Latin wordmark is the brand's large mark and leads the
                     lockup; the Arabic one sits beside it a size down. --}}
                <span class="font-display text-[2.375rem] font-semibold leading-none tracking-[0.05em] text-g-950">MASAR</span>
                <span class="font-display text-[1.5rem] font-bold leading-none text-g-700">{{ setting('identity.site_name') }}</span>
            </a>

            <span class="hidden h-9 w-px shrink-0 bg-line lg:block" aria-hidden="true"></span>

            {{-- Narrow on purpose: the tagline is a stacked block beside the
                 wordmark, not a line running across the bar. --}}
            {{-- Narrow on purpose: a stacked block beside the wordmark, as in the
                 reference, not a line running across the bar. --}}
            <p class="hidden w-20 shrink-0 font-display text-[0.625rem] font-semibold uppercase leading-[1.5] tracking-[0.1em] text-ink-3 lg:block">
                {{ setting('identity.tagline') }}
            </p>

            {{-- The bar carries the nav from xl up. Between lg and xl the eight Arabic
                 labels come to 745px, which does not fit beside a 56px wordmark at
                 1024 — it pushed the burger 242px off the start edge. Below xl the
                 drawer carries navigation, which it already did. --}}
            <x-layout.mega-menu :menu="$menu" class="ms-auto hidden self-stretch xl:flex" />

            {{-- Sits after a vertical hairline on the end side. Below lg the
                 nav is hidden, so it takes the free space itself. --}}
            <div class="ms-auto flex shrink-0 items-center gap-4 xl:ms-4 xl:border-s xl:border-line xl:ps-4">
                <x-layout.mobile-nav :menu="$menu" :locale="$locale" />
            </div>
        </div>
    </x-ui.container>
</header>
