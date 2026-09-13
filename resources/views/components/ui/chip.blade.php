@props(['href' => null, 'active' => false])
<{{ $href ? 'a' : 'span' }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'inline-flex items-center rounded-full border px-3 py-1 text-sm transition',
        'border-g-900 bg-g-900 text-cream' => $active,
        'border-line bg-transparent text-ink hover:border-g-600' => ! $active,
    ]) }}
>{{ $slot }}</{{ $href ? 'a' : 'span' }}>
