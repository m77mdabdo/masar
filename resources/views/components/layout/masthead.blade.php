@props(['locale', 'menu' => null])
@php $home = route('web.home', $locale); @endphp
<header class="sticky top-0 z-40 border-b border-line bg-cream/95 backdrop-blur">
    <x-ui.container>
        <div class="flex h-[4.875rem] items-center justify-between gap-6">
            <div class="flex min-w-0 items-center gap-4">
                <a href="{{ $home }}" class="flex shrink-0 items-baseline gap-2" aria-label="{{ setting('identity.site_name') }}">
                    {{-- The Arabic wordmark leads in an Arabic-first product;
                         the Latin one follows it. --}}
                    <span class="font-display text-[1.95rem] font-semibold leading-none text-g-950">{{ setting('identity.site_name') }}</span>
                    <span class="font-display text-lg leading-none tracking-[0.08em] text-g-700">MASAR</span>
                </a>

                <span class="hidden h-9 w-px bg-line lg:block" aria-hidden="true"></span>

                <p class="hidden max-w-[11rem] font-display text-[0.56rem] font-semibold uppercase leading-[1.7] tracking-[0.14em] text-ink-3 lg:block">
                    {{ setting('identity.tagline') }}
                </p>
            </div>

            <x-layout.mega-menu :menu="$menu" class="hidden lg:flex" />

            <div class="flex shrink-0 items-center gap-3">
                <span class="hidden h-9 w-px bg-line lg:block" aria-hidden="true"></span>
                <x-layout.mobile-nav :menu="$menu" :locale="$locale" />
            </div>
        </div>
    </x-ui.container>
</header>
