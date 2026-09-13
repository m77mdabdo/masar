@props(['section', 'categories' => null, 'href' => null])
@php
    // Category tiles, not article tiles: this row is the site's map. It takes
    // the real categories, and only falls back to the section's articles if an
    // installation somehow has none.
    $tiles = collect($categories)->take(6);
@endphp

<section class="mb-0" aria-labelledby="tiles-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" id="tiles-heading" />

    <div class="grid grid-cols-2 gap-3.5 lg:grid-cols-6">
        @foreach ($tiles as $tile)
            <a href="{{ app(App\Support\EntityUrl::class)->for($tile['category']) }}" class="group block">
                <div class="overflow-hidden rounded-lg bg-line">
                    <x-ui.responsive-image
                        scale="card"
                        :media="$tile['media']"
                        alt=""
                        ratio="card"
                        sizes="(max-width: 1024px) 45vw, 15vw"
                        class="transition duration-700 group-hover:scale-[1.05]"
                    />
                </div>

                <b class="mt-2.5 block font-display text-[0.85rem] font-semibold text-g-950">{{ $tile['category']->name }}</b>
                @php $blurb = $tile['category']->translate('description'); @endphp
                @if (filled($blurb))
                    <p class="mt-1 text-[0.7rem] leading-relaxed text-ink-3">{{ Str::limit($blurb, 52) }}</p>
                @endif

                <span class="mt-2.5 flex h-8 w-8 items-center justify-center rounded-full border border-line text-g-700 transition group-hover:border-g-600 group-hover:bg-g-900 group-hover:text-cream" aria-hidden="true">
                    <svg class="h-3 w-3 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
                </span>
            </a>
        @endforeach
    </div>
</section>
