@props(['locale', 'explore' => null, 'company' => null])
<footer class="border-t border-line bg-cream py-12">
    <x-ui.container>
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-mint"></span>
                    <span class="font-display text-xl font-semibold text-g-900">{{ setting('identity.site_name') }}</span>
                </div>
                <p class="mt-3 text-sm text-ink-3">{{ setting('identity.tagline') }}</p>
            </div>

            <nav aria-labelledby="footer-explore">
                <h2 id="footer-explore" class="mb-3 font-display text-sm font-semibold text-g-900">{{ __('site.footer.explore') }}</h2>
                <ul class="space-y-2 text-sm">
                    @foreach (($explore ?? collect()) as $item)
                        <li><a href="{{ $item['url'] }}" class="inline-flex min-h-6 items-center text-ink-3 transition hover:text-g-700">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-labelledby="footer-company">
                <h2 id="footer-company" class="mb-3 font-display text-sm font-semibold text-g-900">{{ __('site.footer.platform') }}</h2>
                <ul class="space-y-2 text-sm">
                    @foreach (($company ?? collect()) as $item)
                        <li><a href="{{ $item['url'] }}" class="inline-flex min-h-6 items-center text-ink-3 transition hover:text-g-700">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-labelledby="footer-legal">
                <h2 id="footer-legal" class="mb-3 font-display text-sm font-semibold text-g-900">{{ __('site.footer.legal') }}</h2>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('web.editorial-standards', $locale) }}" class="inline-flex min-h-6 items-center text-ink-3 transition hover:text-g-700">{{ __('site.footer.editorial_standards') }}</a></li>
                    <li><a href="{{ route('web.about', $locale) }}" class="inline-flex min-h-6 items-center text-ink-3 transition hover:text-g-700">{{ __('site.footer.privacy') }}</a></li>
                    <li><a href="{{ route('web.about', $locale) }}" class="inline-flex min-h-6 items-center text-ink-3 transition hover:text-g-700">{{ __('site.footer.terms') }}</a></li>
                    <li><a href="{{ route('web.about', $locale) }}" class="inline-flex min-h-6 items-center text-ink-3 transition hover:text-g-700">{{ __('site.footer.cookies') }}</a></li>
                </ul>
            </nav>

            <div>
                <h2 class="mb-3 font-display text-sm font-semibold text-g-900">{{ __('site.footer.follow') }}</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ((array) setting('contact.social', []) as $platform => $url)
                        @continue (! ($social = \App\Support\SafeUrl::external($url)))
                        <li>
                            <a href="{{ $social }}" rel="noopener" class="ltr-isolate inline-flex min-h-6 items-center text-ink-3 transition hover:text-g-700">{{ $platform }}</a>
                        </li>
                    @endforeach
                    @if (setting('contact.editorial_email'))
                        <li>
                            <a href="mailto:{{ setting('contact.editorial_email') }}" class="ltr-isolate inline-flex min-h-6 items-center text-ink-3 transition hover:text-g-700">
                                {{ setting('contact.editorial_email') }}
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- The house line closes the footer, as the prototype has it. --}}
        <p class="mt-8 max-w-[22ch] font-display text-lg leading-snug text-g-950">
            {{ __('site.footer.tagline_1') }}<br>{{ __('site.footer.tagline_2') }}
        </p>

        <x-ui.divider class="my-8" />

        <div class="flex flex-wrap items-center justify-between gap-4 text-xs text-ink-3">
            <p>© <span class="nums-tabular">{{ now()->year }}</span> {{ setting('identity.site_name') }}. {{ __('site.footer.rights') }}</p>

            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('web.editorial-standards', $locale) }}" class="inline-flex min-h-6 items-center transition hover:text-g-700">{{ __('site.footer.editorial_standards') }}</a>
                <a href="{{ route('web.rss.locale', $locale) }}" class="inline-flex min-h-6 items-center px-0.5 transition hover:text-g-700">RSS</a>

                {{-- The editions without JavaScript: each front page, named in
                     its own language, with that language's lang and dir so a
                     screen reader does not read «اردو» in an English voice.
                     The topbar switcher is the in-context one; this wraps. --}}
                @if (count(\App\Support\Locales::enabled()) > 1)
                    <span class="flex flex-wrap items-center gap-1">
                        @foreach (\App\Support\Locales::enabled() as $code)
                            @if (! $loop->first)<span aria-hidden="true">/</span>@endif
                            <a href="{{ route('web.home', $code) }}" hreflang="{{ \App\Support\Locales::tag($code) }}"
                               lang="{{ \App\Support\Locales::tag($code) }}" dir="{{ \App\Support\Locales::dir($code) }}"
                               @if ($code === $locale) aria-current="true" @endif
                               @class(['inline-flex min-h-6 items-center px-0.5 transition hover:text-g-700', 'font-bold text-ink' => $code === $locale])>{{ \App\Support\Locales::name($code) }}</a>
                        @endforeach
                    </span>
                @endif

                {{-- A link, not a scripted button: it works with JavaScript off
                     and it is the same anchor a keyboard user already has. --}}
                <a href="#main" class="inline-flex min-h-6 items-center transition hover:text-g-700">
                    {{ __('site.footer.back_to_top') }} <span aria-hidden="true">↑</span>
                </a>
            </div>
        </div>

        {{-- Credit, on the page. CLAUDE.md §5 requires every photograph to carry
             one, and the licences these are used under require it too. --}}
        <p class="mt-6 text-[0.7rem] leading-relaxed text-ink-3">
            {{ __('site.footer.photo_credits') }}
            {{ __('site.footer.people_photos') }}
        </p>
    </x-ui.container>
</footer>
