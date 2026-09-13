@php $urls = app(App\Support\EntityUrl::class); @endphp

<x-layout.public title="بحث" :noindex="true">
    <x-ui.container class="py-10">
        <h1 class="font-display text-3xl font-semibold text-g-950 sm:text-4xl">بحث</h1>

        <form method="GET" action="{{ route('web.search', app()->getLocale()) }}" class="mt-6 flex gap-2">
            <label for="q" class="sr-only">كلمة البحث</label>
            <input
                id="q" name="q" type="search" value="{{ $term }}"
                placeholder="ابحث في مسار…"
                class="min-w-0 flex-1 rounded-lg border border-line bg-white px-4 py-3 focus:border-g-600 focus:outline-none"
            />
            <x-ui.button type="submit" size="lg">بحث</x-ui.button>
        </form>

        @if ($results === null)
            <div class="mt-12">
                <h2 class="mb-3 font-display text-sm font-semibold uppercase tracking-wide text-ink-3">مواضيع رائجة</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($suggestions as $topic)
                        <x-ui.chip :href="$urls->for($topic)">{{ $topic->name }}</x-ui.chip>
                    @endforeach
                </div>
            </div>
        @elseif ($results->total() === 0)
            <div class="mt-12 rounded-xl border border-line bg-white p-8 text-center">
                <p class="font-display text-lg text-g-900">لا نتائج لـ «{{ $term }}»</p>
                <p class="mt-2 text-ink-3">جرّب كلمة أعم، أو ابدأ من أحد هذه المواضيع.</p>

                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    @foreach ($suggestions as $topic)
                        <x-ui.chip :href="$urls->for($topic)">{{ $topic->name }}</x-ui.chip>
                    @endforeach
                </div>
            </div>
        @else
            <p class="nums-tabular mt-6 text-sm text-ink-3">{{ $results->total() }} نتيجة لـ «{{ $term }}»</p>

            <div class="mt-8 space-y-6">
                @foreach ($results as $article)
                    <x-article.card-row :article="$article" />
                @endforeach
            </div>

            <div class="mt-10">{{ $results->links() }}</div>
        @endif
    </x-ui.container>
</x-layout.public>
