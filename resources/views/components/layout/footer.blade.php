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
                <h2 id="footer-explore" class="mb-3 font-display text-sm font-semibold text-g-900">استكشف</h2>
                <ul class="space-y-2 text-sm">
                    @foreach (($explore ?? collect()) as $item)
                        <li><a href="{{ $item['url'] }}" class="text-ink-3 transition hover:text-g-700">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-labelledby="footer-company">
                <h2 id="footer-company" class="mb-3 font-display text-sm font-semibold text-g-900">المنصة</h2>
                <ul class="space-y-2 text-sm">
                    @foreach (($company ?? collect()) as $item)
                        <li><a href="{{ $item['url'] }}" class="text-ink-3 transition hover:text-g-700">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-labelledby="footer-legal">
                <h2 id="footer-legal" class="mb-3 font-display text-sm font-semibold text-g-900">قانوني</h2>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('web.editorial-standards', $locale) }}" class="text-ink-3 transition hover:text-g-700">معايير التحرير</a></li>
                    <li><a href="{{ route('web.about', $locale) }}" class="text-ink-3 transition hover:text-g-700">سياسة الخصوصية</a></li>
                    <li><a href="{{ route('web.about', $locale) }}" class="text-ink-3 transition hover:text-g-700">شروط الاستخدام</a></li>
                    <li><a href="{{ route('web.about', $locale) }}" class="text-ink-3 transition hover:text-g-700">سياسة ملفات الارتباط</a></li>
                </ul>
            </nav>

            <div>
                <h2 class="mb-3 font-display text-sm font-semibold text-g-900">تابعنا</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ((array) setting('contact.social', []) as $platform => $url)
                        <li>
                            <a href="{{ $url }}" rel="noopener" class="ltr-isolate text-ink-3 transition hover:text-g-700">{{ $platform }}</a>
                        </li>
                    @endforeach
                    @if (setting('contact.editorial_email'))
                        <li>
                            <a href="mailto:{{ setting('contact.editorial_email') }}" class="ltr-isolate text-ink-3 transition hover:text-g-700">
                                {{ setting('contact.editorial_email') }}
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- The house line closes the footer, as the prototype has it. --}}
        <p class="mt-8 max-w-[22ch] font-display text-lg leading-snug text-g-950">
            منظور عالمي.<br>ميزة إقليمية.
        </p>

        <x-ui.divider class="my-8" />

        <div class="flex flex-wrap items-center justify-between gap-4 text-xs text-ink-3">
            <p>© <span class="nums-tabular">{{ now()->year }}</span> {{ setting('identity.site_name') }}. جميع الحقوق محفوظة.</p>

            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('web.editorial-standards', $locale) }}" class="transition hover:text-g-700">معايير التحرير</a>
                <a href="{{ route('web.rss.locale', $locale) }}" class="transition hover:text-g-700">RSS</a>

                <span class="flex items-center gap-1">
                    @foreach (config('masar.locales') as $code => $config)
                        @continue (! ($config['enabled'] ?? false))
                        @if (! $loop->first)<span aria-hidden="true">/</span>@endif
                        <a href="{{ route('web.home', $code) }}"
                           @class(['transition hover:text-g-700', 'font-bold text-ink' => $code === $locale])>{{ $config['name'] }}</a>
                    @endforeach
                </span>

                {{-- A link, not a scripted button: it works with JavaScript off
                     and it is the same anchor a keyboard user already has. --}}
                <a href="#main" class="transition hover:text-g-700">
                    إلى الأعلى <span aria-hidden="true">↑</span>
                </a>
            </div>
        </div>

        {{-- Credit, on the page. CLAUDE.md §5 requires every photograph to carry
             one, and the licences these are used under require it too. --}}
        <p class="mt-6 text-[0.7rem] leading-relaxed text-ink-3">
            الصور: مساهمون في Unsplash وPexels. تُنسب كل صورة إلى مصوّرها في صفحة المادة.
            صور الفرق والخبراء فوتوغرافية حقيقية لأشخاص حقيقيين.
        </p>
    </x-ui.container>
</footer>
