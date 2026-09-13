@props(['figures', 'tone' => 'light'])
@php $asOf = $figures->asOf(); @endphp
@if ($asOf)
    {{-- Every figure on this page is dated and attributed, without exception.
         A number with no "as of" is one a reader will assume is live. --}}
    <p @class([
        'flex flex-wrap items-center gap-x-2 text-[0.7rem]',
        'text-cream/60' => $tone === 'dark',
        'text-ink-3' => $tone !== 'dark',
    ])>
        <span>حتى</span>
        <time datetime="{{ $asOf->toIso8601String() }}" class="ltr-isolate nums-tabular">{{ $asOf->format('Y-m-d H:i') }}</time>
        @if ($figures->source())
            <span aria-hidden="true">·</span>
            <span>{{ $figures->source() }}</span>
        @endif
    </p>
@endif
