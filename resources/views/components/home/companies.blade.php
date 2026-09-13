@props(['section', 'href' => null])
<section class="mb-0" aria-labelledby="companies-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" more="الدليل" id="companies-heading" />

    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-8">
        @foreach ($section['items']->take(8) as $company)
            <a href="{{ app(App\Support\EntityUrl::class)->for($company) }}"
               class="group flex flex-col items-center justify-center gap-1 rounded-lg border border-line bg-white px-2 py-3.5 text-center transition hover:border-g-600">
                @if ($company->logo_path)
                    <img src="{{ $company->logo_path }}" alt="" width="28" height="28" loading="lazy" class="h-7 w-7 object-contain" />
                @else
                    {{-- A wordmark we typeset is ours; a scraped logo is not. --}}
                    <b class="text-balance font-display text-[0.78rem] font-semibold leading-tight text-g-700 transition group-hover:text-g-900">{{ $company->name }}</b>
                @endif
                <span class="text-[0.65rem] leading-tight text-ink-3">{{ $company->industry?->name }}</span>
            </a>
        @endforeach
    </div>
</section>
