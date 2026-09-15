@props(['locale'])
{{-- contrast-safe: this band paints bg-g-900, so every foreground here sits on
     that ground. mint-2 measures 10.78:1 and coral 6.98:1 on the dark band;
     both fail on cream and are never used as text on a light ground. --}}
@php
    // The band renders on every page, and the page may also carry its own
    // subscribe form. `source` is what keeps the result attached to the form
    // that was actually submitted.
    $result = session('newsletter');
    $mine = is_array($result) && ($result['source'] ?? null) === 'band';
@endphp
<section class="bg-g-900 py-12 text-cream" aria-labelledby="newsletter-heading">
    <x-ui.container>
        <div class="grid items-center gap-8 lg:grid-cols-[1fr_1.15fr_0.85fr]">
            <div>
                <h2 id="newsletter-heading" class="font-display text-2xl font-semibold sm:text-3xl">
                    من المعلومة إلى الفرصة، كل أسبوع
                </h2>
                <p class="mt-2 text-cream/70">
                    ملخص أسبوعي لما تغيّر في السوق السعودية، ولماذا يهم.
                </p>
            </div>

            @if ($mine)
                <p class="flex items-start gap-2 rounded-lg bg-cream/10 p-4 text-sm text-mint-2" role="status">
                    <span aria-hidden="true">✓</span>
                    <span>{{ $result['message'] }}</span>
                </p>
            @else
                <form method="POST" action="{{ route('web.newsletter.subscribe', $locale) }}" class="flex flex-col gap-2">
                    @csrf
                    <input type="hidden" name="source" value="band" />

                    {{-- The honeypot. Hidden from sight and from assistive tech,
                         never autofilled, and left empty by any real browser. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="band-company-website">لا تملأ هذا الحقل</label>
                        <input id="band-company-website" type="text" name="company_website" tabindex="-1" autocomplete="off" />
                    </div>

                    <div class="flex gap-2">
                        <label for="newsletter-email" class="sr-only">البريد الإلكتروني</label>
                        <input
                            id="newsletter-email" name="email" type="email" required
                            value="{{ old('email') }}"
                            placeholder="name@example.com" dir="ltr"
                            autocomplete="email"
                            @error('email') aria-invalid="true" aria-describedby="newsletter-email-error" @enderror
                            class="ltr-isolate min-w-0 flex-1 rounded-full border border-cream/25 bg-cream/10 px-4 py-2.5 text-cream placeholder:text-cream/40 focus:border-mint focus:outline-none"
                        />
                        <button type="submit" class="shrink-0 rounded-full bg-cream px-6 py-2.5 font-medium text-g-900 transition hover:bg-mint">
                            اشترك
                        </button>
                    </div>

                    @error('email')
                        <p id="newsletter-email-error" class="text-xs text-coral">{{ $message }}</p>
                    @enderror

                    <p class="text-xs text-cream/65">انضم إلى قرّاء من قادة الأعمال والمستثمرين وصنّاع التغيير.</p>
                </form>
            @endif

            {{-- The house line. It is the promise the whole section makes, and
                 the prototype gives it a column of its own. --}}
            <blockquote class="hidden font-display text-lg italic leading-relaxed text-cream/90 lg:block">
                &ldquo;{{ setting('identity.tagline') }}&rdquo;
                <cite class="mt-3 block font-display text-[0.65rem] font-normal not-italic uppercase tracking-[0.16em] text-cream/55">
                    — {{ setting('identity.site_name') }}
                </cite>
            </blockquote>
        </div>
    </x-ui.container>
</section>
