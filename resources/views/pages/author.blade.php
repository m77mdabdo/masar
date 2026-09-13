@php $urls = app(App\Support\EntityUrl::class); @endphp

<x-layout.public
    :title="$author->name"
    :description="'مواد ' . $author->name . ' في ' . setting('identity.site_name')"
>
    @push('schema')
        <x-schema.person :author="$author" :topics="$topics" />
    @endpush

    <x-ui.container class="py-10">
        <header class="mb-10 border-b border-line pb-8">
            <div class="flex flex-wrap items-start gap-5">
                <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-line font-display text-2xl text-ink-3">
                    {{ mb_substr($author->name, 0, 1) }}
                </span>

                <div class="min-w-0 flex-1">
                    <p class="mb-1 text-sm text-ink-3">كاتب في {{ setting('identity.site_name') }}</p>
                    <h1 class="font-display text-3xl font-semibold text-g-950">{{ $author->name }}</h1>

                    {{-- Credentials, stated as facts we can actually verify from
                         our own records rather than a biography we invented. --}}
                    <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm">
                        <div>
                            <dt class="text-ink-3">مواد منشورة</dt>
                            <dd class="nums-tabular font-display text-lg text-g-900">{{ $totalPublished }}</dd>
                        </div>
                        @if ($firstPublished)
                            <div>
                                <dt class="text-ink-3">ينشر منذ</dt>
                                <dd class="ltr-isolate nums-tabular font-display text-lg text-g-900">
                                    {{ \Illuminate\Support\Carbon::parse($firstPublished)->format('Y') }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            @if ($topics->isNotEmpty())
                <div class="mt-6">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-3">يغطّي</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($topics as $topic)
                            <x-ui.tag :href="$urls->for($topic)">{{ $topic->name }}</x-ui.tag>
                        @endforeach
                    </div>
                </div>
            @endif
        </header>

        <x-ui.section-rule title="أحدث المواد" />

        <div class="space-y-6">
            @forelse ($articles as $article)
                <x-article.card-row :article="$article" />
            @empty
                <p class="py-16 text-center text-ink-3">لا توجد مواد منشورة بعد.</p>
            @endforelse
        </div>

        <div class="mt-10">{{ $articles->links() }}</div>
    </x-ui.container>
</x-layout.public>
