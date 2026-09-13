@props(['text', 'attribution' => null, 'role' => null])
<figure class="not-prose my-10 border-s-4 border-mint ps-6">
    <blockquote class="font-display text-xl leading-relaxed text-g-900 sm:text-2xl">{{ $text }}</blockquote>
    @if ($attribution)
        <figcaption class="mt-3 text-sm text-ink-3">
            <span class="font-medium text-ink">{{ $attribution }}</span>
            @if ($role)<span> — {{ $role }}</span>@endif
        </figcaption>
    @endif
</figure>
