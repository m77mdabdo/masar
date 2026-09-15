<x-layout.public :title="$category->name" :description="$category->translate('description')">
    <x-ui.container class="py-10">
        <header class="mb-10 border-b border-line pb-6">
            <h1 class="font-display text-3xl font-semibold text-g-950 sm:text-4xl">{{ $category->name }}</h1>
            @if ($category->translate('description'))
                <p class="mt-3 max-w-2xl text-ink-3">{{ $category->translate('description') }}</p>
            @endif
        </header>

        @if ($featured->isNotEmpty())
            <x-ui.section-rule title="مختارات" />
            <x-ui.grid :cols="3" class="mb-12">
                @foreach ($featured as $article)
                    {{-- The first card is this page's LCP element. --}}
                    <x-article.card-article :article="$article" :eager="$loop->first" />
                @endforeach
            </x-ui.grid>
        @endif

        <x-ui.section-rule title="أحدث المواد" />

        <div class="space-y-6">
            @forelse ($articles as $article)
                <x-article.card-row :article="$article" />
            @empty
                <p class="py-16 text-center text-ink-3">لا توجد مواد في هذا القسم بعد.</p>
            @endforelse
        </div>

        <div class="mt-10">{{ $articles->links() }}</div>
    </x-ui.container>
</x-layout.public>
