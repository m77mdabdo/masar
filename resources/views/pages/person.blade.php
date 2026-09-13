<x-layout.public :title="$person->name" :description="$person->translate('bio')">
    <x-ui.container class="py-10">
        <header class="mb-10 flex flex-wrap items-start gap-5 border-b border-line pb-6">
            @if ($person->photo_path)
                <img src="{{ $person->photo_path }}" alt="" width="72" height="72" class="h-18 w-18 rounded-full object-cover" />
            @endif
            <div class="min-w-0">
                <h1 class="font-display text-3xl font-semibold text-g-950">{{ $person->name }}</h1>
                <p class="mt-1 text-ink-3">{{ $person->translate('title') }}</p>
                @if ($person->company?->name)
                    <a href="{{ app(App\Support\EntityUrl::class)->for($person->company) }}" class="mt-1 inline-block text-sm text-g-700 hover:underline">
                        {{ $person->company->name }}
                    </a>
                @endif
                @if ($person->translate('bio'))
                    <p class="mt-3 max-w-2xl leading-relaxed text-ink-3">{{ $person->translate('bio') }}</p>
                @endif
            </div>
        </header>

        <x-ui.section-rule title="ورد في" />

        <div class="space-y-6">
            @forelse ($mentions as $mention)
                @php $item = $mention->mentionable; @endphp
                @continue (! $item instanceof App\Models\Article)
                <x-article.card-row :article="$item" />
            @empty
                <p class="py-16 text-center text-ink-3">لا توجد مواد بعد.</p>
            @endforelse
        </div>

        <div class="mt-10">{{ $mentions->links() }}</div>
    </x-ui.container>
</x-layout.public>
