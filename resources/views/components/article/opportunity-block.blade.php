@props(['text', 'ctaLabel' => null, 'ctaUrl' => null])
<aside class="not-prose my-8 rounded-xl border border-gold/40 bg-gold/8 p-5 sm:p-6">
    <h3 class="mb-2 flex items-center gap-2 font-display text-sm font-semibold uppercase tracking-wide text-ink">
        <span class="inline-block h-2 w-2 rounded-full bg-gold"></span>
        الفرصة
    </h3>
    <p class="text-base leading-relaxed">{{ $text }}</p>
    @if ($ctaUrl && $ctaLabel)
        <x-ui.button :href="$ctaUrl" variant="secondary" size="sm" class="mt-4">{{ $ctaLabel }}</x-ui.button>
    @endif
</aside>
