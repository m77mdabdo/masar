<x-filament-panels::page>
    @php
        $board = $this->board();
        $moves = $this->allowedMoves();
    @endphp

    {{-- Horizontal scroll is the right affordance for a board; the page itself
         must never scroll sideways, so the overflow lives here. --}}
    <div
        x-data="{
            dragging: null,
            allowed: @js($moves),
            start(id, from) { this.dragging = { id, from } },
            end() { this.dragging = null },
            canDrop(target) {
                if (! this.dragging) return false
                if (this.dragging.from === target) return false
                return (this.allowed[this.dragging.from] ?? []).includes(target)
            },
            drop(target) {
                if (! this.dragging) return
                const { id, from } = this.dragging
                this.end()
                if (from === target) return
                if (! (this.allowed[from] ?? []).includes(target)) {
                    new FilamentNotification()
                        .danger()
                        .title('انتقال غير مسموح')
                        .body('لا يسمح مسار التحرير بهذا الانتقال.')
                        .send()
                    return
                }
                $wire.moveCard(id, target)
            },
        }"
        @dragend.window="end()"
        class="overflow-x-auto pb-4"
    >
        <div class="flex min-h-[60vh] gap-4" style="min-width: max-content;">
            @foreach ($this->columns() as $status)
                @php $cards = $board[$status->value] ?? collect(); @endphp

                <div
                    class="flex w-72 shrink-0 flex-col rounded-xl bg-gray-100 p-3 dark:bg-white/5"
                    :class="canDrop('{{ $status->value }}')
                        ? 'ring-2 ring-[#7FB69A]'
                        : (dragging && dragging.from !== '{{ $status->value }}' ? 'opacity-50' : '')"
                    @dragover.prevent="canDrop('{{ $status->value }}') && $event.preventDefault()"
                    @drop.prevent="drop('{{ $status->value }}')"
                >
                    <div class="mb-3 flex items-center justify-between px-1">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            {{ $status->label() }}
                        </span>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                            {{ $cards->count() }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-2">
                        @forelse ($cards as $article)
                            <div
                                draggable="true"
                                @dragstart="start({{ $article->id }}, '{{ $status->value }}')"
                                wire:key="card-{{ $article->id }}"
                                class="cursor-grab rounded-lg border border-gray-200 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-white/10 dark:bg-gray-900"
                            >
                                <a
                                    href="{{ $this->editUrl($article) }}"
                                    class="block text-sm font-medium leading-snug text-gray-900 hover:text-[#1E5E3F] dark:text-gray-100"
                                >
                                    {{ \Illuminate\Support\Str::limit($article->title, 70) }}
                                </a>

                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($article->category?->name)
                                        <span
                                            class="rounded px-1.5 py-0.5"
                                            style="background-color: {{ $article->category->color ?? '#E2DED4' }}20;"
                                        >{{ $article->category->name }}</span>
                                    @endif

                                    <span>{{ $article->author?->name }}</span>

                                    @if ($article->is_sponsored)
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-800">مدفوع</span>
                                    @endif
                                </div>

                                @if ($status->value === 'ready')
                                    @php $failures = $article->isPublishable(); @endphp
                                    <div class="mt-2 text-xs">
                                        @if ($failures === [])
                                            <span class="text-[#1E5E3F]">✓ جاهزة للنشر</span>
                                        @else
                                            <span class="text-[#B4522E]">
                                                {{ count($failures) }} متطلب ناقص
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="px-1 py-6 text-center text-xs text-gray-400">لا توجد مواد</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
