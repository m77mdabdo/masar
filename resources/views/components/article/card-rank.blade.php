@props(['article', 'rank' => null, 'marker' => null])
@php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
{{-- Text only. Most-read is ranked, editor's picks is marked with a diamond:
     one is a measurement, the other a choice, and the glyph says which.

     The prototype sets the numeral in a paler grey than `ink-3`. A rank is
     information a sighted reader acts on, not decoration, so it keeps the token
     that passes AA rather than the one that looks lightest. --}}
<article class="group grid grid-cols-[1.875rem_1fr] gap-3 border-b border-line py-3 first:pt-0 last:border-0">
    <span class="font-display text-lg font-semibold leading-6 text-ink-3" aria-hidden="true">
        @if ($rank !== null)
            <span class="nums-tabular">{{ str_pad((string) $rank, 2, '0', STR_PAD_LEFT) }}</span>
        @else
            {{ $marker ?? '◆' }}
        @endif
    </span>

    <h3 class="min-w-0 font-display text-[0.85rem] font-medium text-g-950">
        <a href="{{ $url }}" class="block leading-6 transition hover:text-g-700">{{ $article->title }}</a>
    </h3>
</article>
