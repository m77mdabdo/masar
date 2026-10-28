@props(['locale', 'links' => null, 'alternates' => []])
{{-- Cream, one hairline, one 30px row. --}}
<div class="border-b border-line bg-cream">
    <x-ui.container>
        <div class="flex h-[1.875rem] items-center justify-between gap-4 text-xs">
            @if (app(App\Support\DemoMode::class)->isOn())
                {{-- Visible to a reader, not only to a crawler. Someone who lands
                     on this site should know what they are reading before they
                     quote it — so it takes the descriptor's slot and, unlike the
                     descriptor, shows on a phone too. --}}
                <p role="status" class="flex min-w-0 items-center gap-2 text-ink-3">
                    <span class="shrink-0 rounded-full bg-gold-ink px-2 py-0.5 text-[0.6875rem] font-semibold leading-tight text-cream">{{ __('site.demo.badge') }}</span>
                    <span class="truncate"><span class="sm:hidden">{{ __('site.demo.short') }}</span><span class="hidden sm:inline">{{ __('site.demo.long') }}</span></span>
                </p>
            @else
                {{-- aria-hidden: the masthead carries the same line as real content,
                     and a second copy outside every landmark is furniture a screen
                     reader has to step over. --}}
                <p class="hidden text-ink-3 sm:block" aria-hidden="true">{{ setting('identity.descriptor') }}</p>
            @endif

            <nav class="flex items-center gap-4" aria-label="{{ __('site.nav.quick_links') }}">
                @foreach (collect($links)->take(4) as $link)
                    <a href="{{ $link['url'] }}" class="hidden min-h-6 items-center text-ink transition hover:text-g-700 sm:inline-flex">{{ $link['label'] }}</a>
                @endforeach

                <x-layout.language-switcher :locale="$locale" :alternates="$alternates" />
                {{-- The icon stays 16px; the target is padded to 24×24. It was exactly the
                     icon box, which is the smallest tap target on the site and one of
                     the most used. --}}
                <a href="{{ route('web.search', $locale) }}" class="inline-flex h-6 w-6 items-center justify-center text-ink-3 transition hover:text-g-700" aria-label="{{ __('site.nav.search') }}">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M13.5 13.5L17 17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </a>

                {{-- The active locale is bold, not coloured: it is the current
                     state, and weight says so without needing a hue. --}}
                <span class="flex items-center gap-1 text-ink-3">
                    @foreach (config('masar.locales') as $code => $config)
                        @continue (! ($config['enabled'] ?? false))
                        @if (! $loop->first)<span aria-hidden="true">/</span>@endif
                        <a href="{{ route('web.home', $code) }}"
                           @if ($code === $locale) aria-current="true" @endif
                           @class(['inline-flex min-h-6 items-center px-0.5 transition hover:text-g-700', 'font-bold text-ink' => $code === $locale])>
                            {{ $config['name'] }}
                        </a>
                    @endforeach
                </span>

                <a href="{{ route('web.newsletter', $locale) }}"
                   class="inline-flex min-h-6 items-center rounded-full bg-g-900 px-4 text-xs font-medium text-cream transition hover:bg-g-700">
                    {{ __('site.nav.newsletter') }}
                </a>
            </nav>
        </div>
    </x-ui.container>
</div>
