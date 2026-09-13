@props(['cols' => 3, 'gap' => 'md'])
@php
    $columns = [2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 lg:grid-cols-3', 4 => 'sm:grid-cols-2 lg:grid-cols-4'][$cols] ?? 'sm:grid-cols-2 lg:grid-cols-3';
    $gaps = ['sm' => 'gap-4', 'md' => 'gap-6', 'lg' => 'gap-8'][$gap] ?? 'gap-6';
@endphp
<div {{ $attributes->class(['grid grid-cols-1', $columns, $gaps]) }}>{{ $slot }}</div>
