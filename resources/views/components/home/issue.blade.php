@props(['section', 'stats' => []])
{{-- contrast-safe: mint appears only on the cover, which paints g-950 under its
     own scrim. Mint is 2.11:1 on cream and 7.78:1 on g-950. --}}
@php
    $items = $section['items'];
    $cover = $items->first();
@endphp

<section class="mb-0" aria-labelledby="issue-heading">
    <x-ui.section-rule :title="$section['title']" id="issue-heading" more="كل الأعداد" :href="route('web.home', app()->getLocale())" />

    {{-- White and bordered. The issue is a product, not a dark band — it sits
         on the page the way a magazine sits on a table. --}}
    <div class="grid items-center gap-8 rounded-xl border border-line bg-white p-6 sm:p-8 lg:grid-cols-[1.1fr_0.9fr]">
        <div>
            <span class="font-display text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-ink-3">عدد رقمي خاص</span>

            @if ($cover)
                <h3 class="mt-3 font-display text-[2rem] font-semibold leading-[1.06] text-g-950 sm:text-[2.6rem]">
                    <a href="{{ app(App\Support\EntityUrl::class)->for($cover) }}" class="transition hover:text-g-700">{{ $cover->title }}</a>
                </h3>
                @if ($cover->subtitle)
                    <p class="mt-3 max-w-[42ch] text-[0.85rem] leading-relaxed text-ink-3">{{ $cover->subtitle }}</p>
                @endif
            @endif

            @if (filled($stats))
                {{-- Counted from the issue's own contents. The prototype's
                     24/8/15/6 are mock-up numbers, and a hard-coded figure on a
                     business page is a claim we would have to defend. --}}
                <dl class="mt-6 grid grid-cols-2 gap-y-4 border-t border-line pt-5 sm:grid-cols-4">
                    @foreach ($stats as $label => $value)
                        <div>
                            <dd class="nums-tabular font-display text-2xl font-semibold text-g-950">{{ $value }}</dd>
                            <dt class="mt-0.5 text-[0.7rem] text-ink-3">{{ $label }}</dt>
                        </div>
                    @endforeach
                </dl>
            @endif

            <div class="mt-6 flex flex-wrap gap-2.5">
                @if ($cover)
                    <a href="{{ app(App\Support\EntityUrl::class)->for($cover) }}"
                       class="inline-flex items-center gap-2 rounded-full bg-g-900 px-5 py-2.5 text-xs font-medium text-cream transition hover:bg-g-700">
                        اقرأ العدد
                        <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
                    </a>
                @endif
                <a href="{{ route('web.video.index', app()->getLocale()) }}"
                   class="inline-flex items-center gap-2 rounded-full border border-line px-5 py-2.5 text-xs font-medium text-g-900 transition hover:border-g-600">
                    شاهد المقدمة
                </a>
            </div>
        </div>

        {{-- A real cover: perspective and a rotation, mirrored in RTL so the
             spine falls on the side the reader opens from. --}}
        <div class="flex justify-center">
            <div class="issue-cover relative w-[13.5rem] shrink-0">
                <div class="relative isolate overflow-hidden rounded-sm bg-g-950">
                    <x-ui.responsive-image
                        :media="$cover?->heroMedia"
                        alt=""
                        ratio="cover"
                        :fill="true"
                        sizes="216px"
                        class="-z-10 opacity-70"
                    />
                    <div class="card-scrim absolute inset-0 -z-10" aria-hidden="true"></div>
                    <span class="issue-spine absolute inset-y-0 start-0 w-2.5" aria-hidden="true"></span>

                    <div class="flex aspect-[30/41] flex-col justify-between p-5 text-cream">
                        <span class="font-display text-lg tracking-[0.1em]">{{ setting('identity.site_name') }}</span>
                        <div>
                            <h4 class="font-display text-xl font-semibold leading-tight">{{ Str::limit($cover?->title, 36) }}</h4>
                            <p class="nums-tabular mt-2 font-display text-sm text-cream/80">001</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
