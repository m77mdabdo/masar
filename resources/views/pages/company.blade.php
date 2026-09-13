@php $urls = app(App\Support\EntityUrl::class); @endphp
<x-layout.public :title="$company->name" :description="$company->translate('short_description')">
    <x-ui.container class="py-10">
        <header class="mb-10 flex flex-wrap items-start gap-5 border-b border-line pb-6">
            @if ($company->logo_path)
                <img src="{{ $company->logo_path }}" alt="" width="64" height="64" class="h-16 w-16 rounded-lg object-contain" />
            @endif
            <div class="min-w-0 flex-1">
                <h1 class="font-display text-3xl font-semibold text-g-950">{{ $company->name }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-sm text-ink-3">
                    <x-ui.tag>{{ $company->type?->label() }}</x-ui.tag>
                    @if ($company->industry?->name)<x-ui.tag>{{ $company->industry->name }}</x-ui.tag>@endif
                    @if ($company->country?->name)<x-ui.tag>{{ $company->country->name }}</x-ui.tag>@endif
                    @if ($company->ticker)<span class="ltr-isolate nums-tabular">{{ $company->ticker }}</span>@endif
                </div>
                @if ($company->translate('description'))
                    <p class="mt-3 max-w-2xl leading-relaxed text-ink-3">{{ $company->translate('description') }}</p>
                @endif
            </div>
        </header>

        {{-- The content graph's payoff: every content type that mentions this
             company, newest first, from one indexed query. --}}
        <x-ui.section-rule title="كل ما نشرناه عن هذه الشركة" />

        <div class="space-y-6">
            @forelse ($mentions as $mention)
                @php $item = $mention->mentionable; @endphp
                @continue (! $item)
                @if ($item instanceof App\Models\Article)
                    <x-article.card-row :article="$item" />
                @else
                    <x-entity.opportunity-card :opportunity="$item" />
                @endif
            @empty
                <p class="py-16 text-center text-ink-3">لم نغطِّ هذه الشركة بعد.</p>
            @endforelse
        </div>

        <div class="mt-10">{{ $mentions->links() }}</div>
    </x-ui.container>
</x-layout.public>
