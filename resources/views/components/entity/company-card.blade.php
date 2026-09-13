@props(['company'])
<a href="{{ app(App\Support\EntityUrl::class)->for($company) }}"
   class="group flex items-center gap-3 rounded-xl border border-line bg-white p-4 transition hover:border-g-600">
    @if ($company->logo_path)
        <img src="{{ $company->logo_path }}" alt="" width="40" height="40" loading="lazy"
             class="h-10 w-10 shrink-0 rounded object-contain" />
    @else
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-line font-display text-sm text-ink-3">
            {{ mb_substr($company->name ?? '؟', 0, 1) }}
        </span>
    @endif

    <div class="min-w-0">
        <div class="truncate font-display text-sm font-medium text-g-950">{{ $company->name }}</div>
        <div class="flex flex-wrap gap-x-2 text-xs text-ink-3">
            @if ($company->industry?->name)<span>{{ $company->industry->name }}</span>@endif
            @if ($company->ticker)
                {{-- A ticker is Latin inside Arabic and must not flip the line. --}}
                <span class="ltr-isolate nums-tabular">{{ $company->ticker }}</span>
            @endif
        </div>
    </div>
</a>
