@props(['section', 'categories' => null, 'href' => null, 'tight' => false])
@php
    // Category tiles, not article tiles: this row is the site's map. It takes
    // the real categories, and only falls back to the section's articles if an
    // installation somehow has none.
    $tiles = collect($categories)->take(6);
@endphp

<section class="mb-0" aria-labelledby="tiles-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" id="tiles-heading" :tight="$tight" />

    <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($tiles as $tile)
            {{-- A bordered white card with square corners and no shadow. The
                 photograph runs to the card's own edge at the top; the padding
                 starts under it. --}}
            <a href="{{ app(App\Support\EntityUrl::class)->for($tile['category']) }}"
               class="group flex flex-col border border-line bg-white transition hover:border-g-600">
                <div class="overflow-hidden bg-line">
                    <x-ui.responsive-image
                        scale="card"
                        :media="$tile['media']"
                        alt=""
                        ratio="card"
                        sizes="(max-width: 1024px) 45vw, 15vw"
                        class="transition duration-700 group-hover:scale-[1.05]"
                    />
                </div>

                <div class="flex flex-1 flex-col p-3.5">
                    <b class="block font-display text-[1.375rem] font-semibold leading-[1.2] text-g-950">{{ $tile['category']->name }}</b>

                    @php $blurb = $tile['category']->translate('description'); @endphp
                    @if (filled($blurb))
                        <p class="mt-2 line-clamp-2 text-[0.78125rem] leading-[1.55] text-ink-3">{{ $blurb }}</p>
                    @endif

                    {{-- Bottom of the card on the start side, whatever the
                         description's length — mt-auto keeps six tiles with six
                         different blurbs sharing one baseline. --}}
                    <span class="mt-auto block pt-3.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-g-900 text-cream transition group-hover:bg-g-600" aria-hidden="true">
                            <svg class="h-3 w-3 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
                        </span>
                    </span>
                </div>
            </a>
        @endforeach
    </div>
</section>
