<x-layout.public :title="$topic->name" :description="$topic->translate('description')">
    <x-ui.container class="py-10">
        <header class="mb-10 border-b border-line pb-6">
            <p class="mb-1 text-sm text-ink-3">موضوع</p>
            <h1 class="font-display text-3xl font-semibold text-g-950 sm:text-4xl">{{ $topic->name }}</h1>
            @if ($topic->translate('description'))
                <p class="mt-3 max-w-2xl text-ink-3">{{ $topic->translate('description') }}</p>
            @endif
            <p class="nums-tabular mt-2 text-sm text-ink-3">{{ $topic->articles_count }} مادة</p>
        </header>

        <div class="space-y-6">
            @forelse ($articles as $article)
                <x-article.card-row :article="$article" />
            @empty
                <p class="py-16 text-center text-ink-3">لا توجد مواد في هذا الموضوع بعد.</p>
            @endforelse
        </div>

        <div class="mt-10">{{ $articles->links() }}</div>
    </x-ui.container>
</x-layout.public>
