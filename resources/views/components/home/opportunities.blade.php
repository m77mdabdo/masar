@props(['section', 'href' => null, 'mediaPool' => null])
{{-- contrast-safe: each card renders on the card scrim, which guarantees a
     g-950 ground. Mint is 2.07:1 on cream and 7.63:1 on g-950. --}}
<section class="mb-0" aria-labelledby="opportunities-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" more="رادار الفرص" id="opportunities-heading" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($section['items']->take(6) as $opportunity)
            @php
                $url = app(App\Support\EntityUrl::class)->for($opportunity);
                // Opportunities carry no media of their own. Each card takes a
                // distinct photograph from the pool so no two repeat in the row.
                $media = collect($mediaPool)->get($loop->index);
                $potential = $opportunity->potential;
                $tone = $potential?->colour();
            @endphp
            <a href="{{ $url }}" class="group relative isolate block overflow-hidden rounded-xl bg-g-950 text-cream">
                <x-ui.responsive-image
                    :media="$media"
                    alt=""
                    ratio="card"
                    :fill="true"
                    sizes="(max-width: 640px) 100vw, 33vw"
                    class="-z-10 opacity-75 transition duration-700 group-hover:scale-[1.04] group-hover:opacity-85"
                />
                <div class="card-scrim absolute inset-0 -z-10" aria-hidden="true"></div>

                @if ($potential)
                    <span @class([
                        'absolute start-3.5 top-3.5 rounded-full border px-2.5 py-0.5 text-[0.6rem] font-bold uppercase tracking-[0.08em]',
                        'border-mint/60 bg-mint/20 text-cream' => $tone === 'mint',
                        'border-gold/55 bg-gold/20 text-cream' => $tone === 'gold',
                        'border-cream/35 bg-cream/10 text-cream' => ! in_array($tone, ['mint', 'gold'], true),
                    ])>{{ $potential->label() }}</span>
                @endif

                <div class="flex min-h-[11.875rem] flex-col justify-end gap-1 p-4.5">
                    <h3 class="font-display text-base font-semibold leading-snug">{{ $opportunity->title }}</h3>
                    <p class="text-[0.72rem] text-cream/70">
                        @if ($opportunity->industry?->name){{ $opportunity->industry->name }}@endif
                        @if ($opportunity->deadline)
                            <span aria-hidden="true"> · </span>
                            <time datetime="{{ $opportunity->deadline->toDateString() }}" class="ltr-isolate nums-tabular">{{ $opportunity->deadline->format('Y-m-d') }}</time>
                        @endif
                    </p>
                </div>
            </a>
        @endforeach
    </div>
</section>
