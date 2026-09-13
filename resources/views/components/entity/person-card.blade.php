@props(['person'])
<a href="{{ app(App\Support\EntityUrl::class)->for($person) }}"
   class="group flex items-center gap-3 rounded-xl border border-line bg-white p-4 transition hover:border-g-600">
    @if ($person->photo_path)
        <img src="{{ $person->photo_path }}" alt="" width="44" height="44" loading="lazy" class="h-11 w-11 shrink-0 rounded-full object-cover" />
    @else
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-line font-display text-sm text-ink-3">
            {{ mb_substr($person->name ?? '؟', 0, 1) }}
        </span>
    @endif
    <div class="min-w-0">
        <div class="truncate font-display text-sm font-medium text-g-950">{{ $person->name }}</div>
        <div class="truncate text-xs text-ink-3">{{ $person->translate('title') }}</div>
    </div>
</a>
