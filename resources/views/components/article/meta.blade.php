@props(['article', 'compact' => false])
@php
    $category = $article->category;
    $categoryUrl = $category ? app(App\Support\EntityUrl::class)->for($category) : null;
@endphp
<div {{ $attributes->class(['flex flex-wrap items-center gap-x-3 text-xs text-ink-3']) }}>
    @if ($category)
        {{-- inline-flex with a 24px minimum: at 12px the text box is 16px tall,
             which is under the 24x24 target WCAG 2.2 asks for. The padding is
             vertical only, so the metadata line does not visibly change. --}}
        <a href="{{ $categoryUrl }}"
           class="inline-flex min-h-6 items-center font-medium text-g-600 transition hover:text-g-900">{{ $category->name }}</a>
    @endif

    @if ($article->published_at)
        {{-- Dates are Latin digits inside Arabic text and must be isolated. --}}
        <time datetime="{{ $article->published_at->toIso8601String() }}" class="ltr-isolate nums-tabular">
            {{ $article->published_at->format('Y-m-d') }}
        </time>
    @endif

    @unless ($compact)
        @if ($article->reading_time)
            <span class="nums-tabular">{{ $article->reading_time }} دقيقة قراءة</span>
        @endif

        @if ($article->is_sponsored)
            <x-ui.badge tone="gold">محتوى مدفوع</x-ui.badge>
        @endif
    @endunless
</div>
