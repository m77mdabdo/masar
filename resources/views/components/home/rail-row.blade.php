@props(['cols' => 3])
{{-- A band of columns that each carry their own rule. Kept as one grid rather
     than separate sections in the page flow so the row reads as a single band on
     desktop and stacks in the same order on a phone. --}}
<div @class([
    'mb-0 grid gap-8 lg:gap-9',
    'md:grid-cols-2 lg:grid-cols-3' => $cols === 3,
    'lg:grid-cols-[1.25fr_0.75fr]' => $cols === 2,
])>
    {{ $slot }}
</div>
