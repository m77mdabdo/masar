@props(['href', 'underline' => true])
<a href="{{ $href }}" {{ $attributes->class(['text-g-700 transition hover:text-g-900', $underline ? 'underline underline-offset-4 decoration-line hover:decoration-g-600' : '']) }}>
    {{ $slot }}
</a>
