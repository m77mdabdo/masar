@props(['locale', 'links' => null])
{{-- Cream, one hairline, one 42px row. --}}
<div class="border-b border-line bg-cream">
    <x-ui.container>
        <div class="flex h-[3rem] items-center justify-between gap-4 text-xs">
            {{-- aria-hidden: the masthead carries the same line as real content,
                 and a second copy outside every landmark is furniture a screen
                 reader has to step over. --}}
            <p class="hidden text-ink-3 sm:block" aria-hidden="true">{{ setting('identity.descriptor') }}</p>

            <nav class="flex items-center gap-4" aria-label="روابط سريعة">
                @foreach (collect($links)->take(4) as $link)
                    <a href="{{ $link['url'] }}" class="hidden text-ink transition hover:text-g-700 sm:inline">{{ $link['label'] }}</a>
                @endforeach

                <a href="{{ route('web.search', $locale) }}" class="text-ink-3 transition hover:text-g-700" aria-label="بحث">
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
                           @class(['transition hover:text-g-700', 'font-bold text-ink' => $code === $locale])>
                            {{ $config['name'] }}
                        </a>
                    @endforeach
                </span>

                <a href="{{ route('web.newsletter', $locale) }}"
                   class="inline-flex items-center rounded-full bg-g-900 px-4 py-1.5 text-xs font-medium text-cream transition hover:bg-g-700">
                    النشرة
                </a>
            </nav>
        </div>
    </x-ui.container>
</div>
