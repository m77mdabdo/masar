@props(['article', 'url', 'tone' => 'light'])
{{-- contrast-safe: mint only under tone=dark, which the hero scrim backs. Mint is 2.11:1 on cream and 7.78:1 on g-950. --}}
@php
    $dark = $tone === 'dark';
@endphp
{{-- Author, date, reading time and share on one line.

     A real photograph when the author has one — the prototype is explicit that
     portraits must be real photographs of real people — and a monogram when
     they do not. A stock silhouette is a request that tells the reader nothing. --}}
<div {{ $attributes->class([
    'flex flex-wrap items-center justify-between gap-4',
    'border-y border-line py-4' => ! $dark,
]) }}>
    <div class="flex min-w-0 items-center gap-3">
        @if ($article->author?->photo_path)
            <img src="{{ $article->author->photo_path }}" alt="" width="44" height="44" loading="lazy"
                 class="h-11 w-11 shrink-0 rounded-full object-cover" />
        @else
            <span @class([
                'flex h-11 w-11 shrink-0 items-center justify-center rounded-full font-display text-sm',
                'bg-cream/15 text-cream' => $dark,
                'bg-g-900 text-cream' => ! $dark,
            ]) aria-hidden="true">{{ mb_substr($article->author?->name ?? '؟', 0, 1) }}</span>
        @endif

        <div class="min-w-0 text-sm">
            @if ($article->author)
                <a href="{{ app(App\Support\EntityUrl::class)->for($article->author) }}"
                   @class([
                       'block truncate font-medium transition',
                       'text-cream hover:text-mint' => $dark,
                       'text-g-900 hover:text-g-700' => ! $dark,
                   ])>{{ $article->author->name }}</a>
            @endif

            <div @class([
                'flex flex-wrap items-center gap-x-3',
                'text-cream/65' => $dark,
                'text-ink-3' => ! $dark,
            ])>
                @if ($article->published_at)
                    <time datetime="{{ $article->published_at->toIso8601String() }}" class="ltr-isolate nums-tabular">
                        {{ $article->published_at->format('Y-m-d') }}
                    </time>
                @endif
                <span class="nums-tabular">{{ $article->reading_time }} دقيقة قراءة</span>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <x-article.share :article="$article" :url="$url" variant="icons" :tone="$tone" />
        <x-article.fact-check-badge :article="$article" :tone="$tone" />
    </div>
</div>
