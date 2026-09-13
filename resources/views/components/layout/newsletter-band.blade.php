@props(['locale'])
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

            <form method="GET" action="{{ route('web.newsletter', $locale) }}" class="flex flex-col gap-2">
                <div class="flex gap-2">
                <label for="newsletter-email" class="sr-only">البريد الإلكتروني</label>
                <input
                    id="newsletter-email" name="email" type="email" required
                    placeholder="name@example.com" dir="ltr"
                    class="ltr-isolate min-w-0 flex-1 rounded-full border border-cream/25 bg-cream/10 px-4 py-2.5 text-cream placeholder:text-cream/40 focus:border-mint focus:outline-none"
                />
                <button type="submit" class="shrink-0 rounded-full bg-cream px-6 py-2.5 font-medium text-g-900 transition hover:bg-mint">
                    اشترك
                </button>
                </div>
                <p class="text-xs text-cream/65">انضم إلى قرّاء من قادة الأعمال والمستثمرين وصنّاع التغيير.</p>
            </form>

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
