@php $urls = app(App\Support\EntityUrl::class); @endphp

<x-layout.public :title="$issue->name" :description="$issue->translate('description')">
    <x-ui.container class="py-10">
        <header class="mb-10">
            <p class="mb-2 text-sm font-medium text-g-600">ملف العدد</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-g-950 sm:text-5xl">{{ $issue->name }}</h1>
            @if ($issue->translate('description'))
                <p class="mt-4 max-w-2xl text-lg leading-relaxed text-ink-3">{{ $issue->translate('description') }}</p>
            @endif

            <dl class="mt-6 flex flex-wrap gap-x-10 gap-y-3 border-y border-line py-4">
                <div>
                    <dt class="text-xs text-ink-3">مواد</dt>
                    <dd class="nums-tabular font-display text-xl text-g-900">{{ $issue->articles_count }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-ink-3">أقسام</dt>
                    <dd class="nums-tabular font-display text-xl text-g-900">{{ $chapters->count() }}</dd>
                </div>
            </dl>
        </header>

        @if ($cover)
            <section class="mb-14">
                <x-article.card-overlay :article="$cover" size="lg" />
            </section>
        @endif

        @if ($articles->isNotEmpty())
            <section class="mb-14">
                <x-ui.section-rule title="في هذا العدد" />
                <x-ui.grid :cols="3">
                    @foreach ($articles->take(6) as $article)
                        <x-article.card-article :article="$article" />
                    @endforeach
                </x-ui.grid>
            </section>
        @endif

        @foreach ($chapters as $chapter => $chapterArticles)
            @continue ($chapterArticles->isEmpty())
            <section class="mb-12">
                <x-ui.section-rule :title="$chapter" />
                <div class="space-y-5">
                    @foreach ($chapterArticles as $article)
                        <x-article.card-row :article="$article" />
                    @endforeach
                </div>
            </section>
        @endforeach
    </x-ui.container>
</x-layout.public>
