@if ($paginator->hasPages())
    {{-- Laravel's bundled pagination views use ml-/mr- utilities, which break in
         RTL. This one uses logical properties throughout. --}}
    <nav role="navigation" aria-label="{{ __('site.pagination.label') }}" class="flex items-center justify-between gap-4">
        <div class="flex flex-1 items-center justify-between gap-2">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg border border-line px-4 py-2 text-sm text-ink-3/50">{{ __('site.pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="rounded-lg border border-line px-4 py-2 text-sm transition hover:border-g-600">{{ __('site.pagination.previous') }}</a>
            @endif

            <span class="nums-tabular text-sm text-ink-3">
                {{ __('site.pagination.page_of', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="rounded-lg border border-line px-4 py-2 text-sm transition hover:border-g-600">{{ __('site.pagination.next') }}</a>
            @else
                <span class="rounded-lg border border-line px-4 py-2 text-sm text-ink-3/50">{{ __('site.pagination.next') }}</span>
            @endif
        </div>
    </nav>
@endif
