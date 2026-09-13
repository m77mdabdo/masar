@if ($paginator->hasPages())
    {{-- Laravel's bundled pagination views use ml-/mr- utilities, which break in
         RTL. This one uses logical properties throughout. --}}
    <nav role="navigation" aria-label="التنقل بين الصفحات" class="flex items-center justify-between gap-4">
        <div class="flex flex-1 items-center justify-between gap-2">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg border border-line px-4 py-2 text-sm text-ink-3/50">السابق</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="rounded-lg border border-line px-4 py-2 text-sm transition hover:border-g-600">السابق</a>
            @endif

            <span class="nums-tabular text-sm text-ink-3">
                صفحة {{ $paginator->currentPage() }} من {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="rounded-lg border border-line px-4 py-2 text-sm transition hover:border-g-600">التالي</a>
            @else
                <span class="rounded-lg border border-line px-4 py-2 text-sm text-ink-3/50">التالي</span>
            @endif
        </div>
    </nav>
@endif
