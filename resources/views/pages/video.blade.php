@php
    $urls = app(App\Support\EntityUrl::class);
    $featuredVideo = $featured?->blocks->firstWhere('type', 'video');

    // Self-hosted clips are ours to serve; anything else is a third-party embed
    // and only ever loads behind a click.
    $featuredClip = $featured?->getFirstMedia('video');
@endphp

<x-layout.public title="مرئيات" description="تقارير ومقابلات مصوّرة من مسار.">
    <x-ui.container class="py-10">
        <header class="mb-8 border-b border-line pb-6">
            <h1 class="font-display text-3xl font-semibold text-g-950 sm:text-4xl">مرئيات</h1>
            <p class="mt-2 text-ink-3">تقارير ومقابلات مصوّرة.</p>
        </header>

        @if ($featured && $featuredVideo)
            <section class="mb-12 grid gap-6 lg:grid-cols-[1.4fr_1fr] lg:gap-10">
                @if ($featuredClip)
                    <x-ui.video-player
                        :media="$featuredClip"
                        :poster="$featured->getFirstMedia('video_poster') ?? $featured->heroMedia"
                        :title="$featured->title"
                        ratio="hero"
                    />
                @else
                    <x-ui.video-facade
                        :url="$featuredVideo->data['url'] ?? ''"
                        :title="$featured->title"
                        :poster="$featured->heroMedia"
                    />
                @endif

                <div class="flex flex-col justify-center">
                    <x-article.meta :article="$featured" class="mb-3" />
                    <h2 class="font-display text-2xl font-semibold leading-tight text-g-950 sm:text-3xl">
                        <a href="{{ $urls->for($featured) }}" class="transition hover:text-g-700">{{ $featured->title }}</a>
                    </h2>
                    @if ($featured->subtitle)
                        <p class="mt-3 leading-relaxed text-ink-3">{{ $featured->subtitle }}</p>
                    @endif
                </div>
            </section>
        @endif

        @if ($series->isNotEmpty())
            <section class="mb-10">
                <h2 class="mb-3 font-display text-sm font-semibold uppercase tracking-wide text-ink-3">سلاسل</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($series as $topic)
                        <x-ui.chip :href="$urls->for($topic)">{{ $topic->name }}</x-ui.chip>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <x-ui.section-rule title="كل المرئيات" />

            <x-ui.grid :cols="3">
                @forelse ($videos as $video)
                    <x-article.card-article :article="$video" />
                @empty
                    <p class="col-span-full py-16 text-center text-ink-3">لا توجد مرئيات بعد.</p>
                @endforelse
            </x-ui.grid>

            <div class="mt-10">{{ $videos->links() }}</div>
        </section>
    </x-ui.container>
</x-layout.public>
