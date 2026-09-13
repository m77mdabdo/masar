@props(['sources', 'article' => null])
@if ($sources->isNotEmpty())
    {{-- The trust layer, shown rather than buried: every claim MASAR publishes
         is traceable to something a reader can open themselves. --}}
    <div class="not-prose grid gap-6 rounded-xl border border-line bg-white p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-6">
        <ol class="space-y-3">
            @foreach ($sources as $source)
                <li class="flex gap-3 text-sm">
                    <span class="nums-tabular mt-0.5 text-ink-3">{{ $loop->iteration }}.</span>
                    <div class="min-w-0">
                        @if ($source->url)
                            <a href="{{ $source->url }}" rel="nofollow noopener" target="_blank"
                               class="font-medium text-g-700 underline underline-offset-4 transition hover:text-g-900">
                                {{ $source->title }}
                            </a>
                        @else
                            <span class="font-medium">{{ $source->title }}</span>
                        @endif

                        <div class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-ink-3">
                            @if ($source->publisher)<span>{{ $source->publisher }}</span>@endif
                            @if ($source->accessed_at)
                                <span class="ltr-isolate nums-tabular">{{ $source->accessed_at->format('Y-m-d') }}</span>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>

        @if ($article?->fact_checked_at)
            {{-- Who checked it and when, beside the list rather than under it:
                 the sources and the check are one claim about this article. --}}
            <div class="flex items-start gap-3 border-t border-line pt-4 sm:border-s sm:border-t-0 sm:ps-6 sm:pt-0">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-g-600 text-cream" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                <div class="text-xs leading-relaxed">
                    <p class="font-display text-sm font-semibold text-g-950">دُقّقت المعلومات</p>
                    @if ($article->factChecker)
                        <p class="text-ink-3">{{ $article->factChecker->name }}</p>
                    @endif
                    <time datetime="{{ $article->fact_checked_at->toIso8601String() }}" class="ltr-isolate nums-tabular text-ink-3">
                        {{ $article->fact_checked_at->format('Y-m-d') }}
                    </time>
                </div>
            </div>
        @endif
    </div>
@endif
