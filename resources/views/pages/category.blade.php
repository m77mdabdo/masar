@php
    $locale = app()->getLocale();

    /*
     * The shape follows the supply, rather than the supply being padded to fill
     * a shape. Measured before this existed: three of the six categories had
     * fewer than three featured articles and rendered a three-across grid with
     * holes in it — saudi with one, business and global with two.
     *
     * `global` is the case that makes the rule explicit. It carries 7 articles
     * because global coverage only runs when it touches the Saudi market, so it
     * is *supposed* to be thin. A layout that reads deliberately at 7 is the
     * answer; more articles is not.
     */
    $shape = match ($featured->count()) {
        0 => 'none',
        1 => 'single',
        2 => 'pair',
        default => 'grid',
    };
@endphp

<x-layout.public :title="$category->name" :description="$category->translate('description')">
    <x-ui.container class="py-10">
        <header class="mb-10 border-b border-line pb-6">
            <h1 class="font-display text-3xl font-semibold text-g-950 sm:text-4xl">{{ $category->name }}</h1>
            @if ($category->translate('description'))
                <p class="mt-3 max-w-2xl text-ink-3">{{ $category->translate('description') }}</p>
            @endif
        </header>

        @if ($shape !== 'none')
            <x-ui.section-rule title="مختارات" />

            @if ($shape === 'single')
                {{-- One feature, given the room a single thing deserves rather
                     than a third of a row with two gaps beside it. --}}
                <div class="mb-12">
                    <x-article.card-overlay :article="$featured->first()" size="lg" :level="2" :eager="true" />
                </div>
            @elseif ($shape === 'pair')
                <x-ui.grid :cols="2" class="mb-12">
                    @foreach ($featured as $article)
                        <x-article.card-article :article="$article" :eager="$loop->first" />
                    @endforeach
                </x-ui.grid>
            @else
                <x-ui.grid :cols="3" class="mb-12">
                    @foreach ($featured as $article)
                        {{-- The first card is this page's LCP element. --}}
                        <x-article.card-article :article="$article" :eager="$loop->first" />
                    @endforeach
                </x-ui.grid>
            @endif
        @endif

        <x-ui.section-rule title="أحدث المواد" />

        <div class="space-y-6">
            @forelse ($articles as $article)
                {{-- With no feature above it, the first row carries the LCP. --}}
                <x-article.card-row :article="$article" />
            @empty
                <p class="py-16 text-center text-ink-3">لا توجد مواد في هذا القسم بعد.</p>
            @endforelse
        </div>

        @if ($articles->hasPages())
            <div class="mt-10">{{ $articles->links() }}</div>
        @endif
    </x-ui.container>
</x-layout.public>
