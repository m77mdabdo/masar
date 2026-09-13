@props(['title', 'id'])
<section class="rounded-xl border border-line bg-white" aria-labelledby="{{ $id }}">
    <h2 id="{{ $id }}" class="border-b border-line px-4 py-3 font-display text-xs font-semibold uppercase tracking-[0.12em] text-g-900">
        {{ $title }}
    </h2>
    {{ $slot }}
</section>
