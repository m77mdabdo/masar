@props(['category'])
{{-- contrast-safe: renders on g-900. Mint is 2.07:1 on cream and 7.63:1 on g-950. --}}
{{-- Renders on g-900. Mint is correct as a foreground here (7.63:1) and wrong
     on cream (2.11) — which is why this is its own file: the contrast test
     allows the token by component, not by page. --}}
<a href="{{ app(App\Support\EntityUrl::class)->for($category) }}"
   class="block overflow-hidden rounded-xl bg-g-900 p-5 text-cream transition hover:bg-g-950">
    <span class="font-display text-xs font-semibold uppercase tracking-wide text-mint">استكشف</span>
    <h2 class="mt-1 font-display text-lg font-semibold">{{ $category->name }}</h2>
    @if (filled($category->description))
        <p class="mt-2 text-sm leading-relaxed text-cream/75">{{ Str::limit($category->description, 90) }}</p>
    @endif
</a>
