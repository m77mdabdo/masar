@props(['section'])
@php
    // Reports are articles of type `report` until a reports table exists. The
    // row shows what a reader gets — a document — rather than pretending a file
    // is attached when none is.
    $items = $section['items']->take(4);
@endphp
<section aria-labelledby="rail-reports-heading">
    <x-ui.section-rule :title="$section['title']" id="rail-reports-heading" :tight="true" />

    <div>
        @foreach ($items as $article)
            @php $url = app(App\Support\EntityUrl::class)->for($article); @endphp
            <a href="{{ $url }}" class="group grid grid-cols-[2rem_1fr_1.125rem] items-center gap-3.5 border-b border-line py-3 last:border-0">
                <span class="flex h-8 w-8 items-center justify-center rounded bg-mint/25 text-g-950" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 3h7l5 5v13H7zM14 3v5h5"/>
                    </svg>
                </span>

                <span class="min-w-0">
                    <b class="block font-display text-[0.85rem] font-semibold leading-6 text-g-950 transition group-hover:text-g-700">{{ $article->title }}</b>
                    <span class="nums-tabular text-[0.7rem] text-ink-3">{{ $article->reading_time }} دقيقة قراءة</span>
                </span>

                <span class="text-ink-3 transition group-hover:text-g-700" aria-hidden="true">↓</span>
            </a>
        @endforeach
    </div>
</section>
