@props(['wide' => false])
{{-- Side padding lives here, once, so no page can lose its gutter. --}}
<div {{ $attributes->class(['mx-auto w-full px-5 sm:px-[1.875rem]', $wide ? 'max-w-[1400px]' : 'max-w-[1300px]']) }}>
    {{ $slot }}
</div>
