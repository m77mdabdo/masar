@props(['companies'])
@if ($companies->isNotEmpty())
    {{-- Bordered cells, not chips. The wordmark is the company's name typeset in
         our own face — ours to render — with the real logo used only where one
         has been supplied. A scraped logo is not ours to publish. --}}
    <div class="not-prose grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($companies->take(6) as $company)
            <a href="{{ app(App\Support\EntityUrl::class)->for($company) }}"
               class="group flex flex-col items-center gap-1.5 rounded-lg border border-line bg-white px-2 py-4 text-center transition hover:border-g-600">
                @if ($company->logo_path)
                    <img src="{{ $company->logo_path }}" alt="" width="28" height="28" loading="lazy"
                         class="h-7 w-7 object-contain" />
                @else
                    {{-- Wrapped, never truncated: a company's name cut mid-word
                         is worse than a cell two lines tall. --}}
                    <span class="text-balance font-display text-[0.8rem] font-semibold leading-tight text-g-700 transition group-hover:text-g-900">
                        {{ $company->name }}
                    </span>
                @endif

                <span class="text-[0.7rem] leading-tight text-ink-3">{{ $company->industry?->name }}</span>
            </a>
        @endforeach
    </div>
@endif
