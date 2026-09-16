@props(['placement'])
@php
    $server = app(App\Support\AdServer::class);
    $creative = $server->forPlacement($placement);

    if ($creative !== null) {
        $server->recordImpression($creative);
    }
@endphp

{{-- Renders nothing at all when nothing is running. An empty bordered box
     labelled "advertisement" is a worse page than no box. --}}
@if ($creative !== null)
    <aside class="my-8" aria-label="إعلان">
        {{-- The label is outside the link and not a prop: an advertisement is
             marked as one whatever the creative says, and a caller cannot turn
             it off (CLAUDE.md §5). --}}
        <div class="mb-1.5 flex items-baseline justify-between gap-3">
            <span class="text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-ink-3">إعلان</span>
            <span class="truncate text-[0.65rem] text-ink-3">{{ $creative->campaign->advertiser }}</span>
        </div>

        <a href="{{ route('web.ads.click', ['locale' => app()->getLocale(), 'creative' => $creative->getKey()]) }}"
           {{-- nofollow and sponsored: paid links must not pass ranking signals,
                and `sponsored` is the value Google documents for exactly this. --}}
           rel="nofollow sponsored noopener"
           target="_blank"
           class="block overflow-hidden rounded-xl border border-line bg-white">
            <img src="{{ $creative->image_path }}"
                 alt="{{ $creative->alt }}"
                 loading="lazy" decoding="async"
                 class="h-auto w-full" />
        </a>
    </aside>
@endif
