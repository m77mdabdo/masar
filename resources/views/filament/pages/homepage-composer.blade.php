<x-filament-panels::page>
    @php
        $layouts = $this->layouts();
        $sections = $this->sections();
        $composed = $this->composed()->keyBy('id');
        $current = $this->currentLayout();
    @endphp

    {{-- Layout switcher --}}
    <div class="flex flex-wrap gap-2">
        @foreach ($layouts as $layout)
            <button
                type="button"
                wire:click="selectLayout({{ $layout->id }})"
                @class([
                    'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                    'bg-[#0E2A1C] text-white' => $this->layoutId === $layout->id,
                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300' => $this->layoutId !== $layout->id,
                ])
            >
                {{ $layout->name }}
                @if ($layout->is_active)
                    <span class="ms-1 rounded bg-[#7FB69A] px-1.5 py-0.5 text-[10px] text-[#0E2A1C]">نشط</span>
                @endif
                <span class="ms-1 opacity-60">({{ $layout->sections_count }})</span>
            </button>
        @endforeach
    </div>

    @if ($current?->starts_at || $current?->ends_at)
        <div class="rounded-lg border border-[#C9A063]/40 bg-[#C9A063]/10 px-3 py-2 text-sm">
            مجدول:
            {{ $current->starts_at?->format('Y-m-d H:i') ?? 'الآن' }}
            →
            {{ $current->ends_at?->format('Y-m-d H:i') ?? 'بلا نهاية' }}
        </div>
    @endif

    @if ($sections->isEmpty())
        <p class="py-10 text-center text-sm text-gray-400">لا توجد أقسام في هذا التخطيط بعد.</p>
    @else
        <div
            class="space-y-3"
            x-data="{
                order: @js($sections->pluck('id')->all()),
                dragging: null,
                start(id) { this.dragging = id },
                drop(targetId) {
                    if (! this.dragging || this.dragging === targetId) return
                    const from = this.order.indexOf(this.dragging)
                    const to = this.order.indexOf(targetId)
                    this.order.splice(to, 0, this.order.splice(from, 1)[0])
                    this.dragging = null
                    $wire.reorderSections(this.order)
                },
            }"
        >
            @foreach ($sections as $section)
                @php
                    $type = \App\Enums\HomepageSectionType::tryFrom($section->type);
                    $resolved = $composed[$section->id] ?? null;
                    $items = $resolved['items'] ?? collect();
                @endphp

                <div
                    draggable="true"
                    x-on:dragstart="start({{ $section->id }})"
                    x-on:dragover.prevent
                    x-on:drop.prevent="drop({{ $section->id }})"
                    wire:key="section-{{ $section->id }}"
                    @class([
                        'fi-section rounded-xl border p-4 cursor-grab active:cursor-grabbing',
                        'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900' => $section->is_visible,
                        'border-dashed border-gray-300 bg-gray-50 opacity-70 dark:bg-white/5' => ! $section->is_visible,
                    ])
                >
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <x-filament::icon icon="heroicon-o-bars-2" class="h-4 w-4 text-gray-400" />

                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $section->title() ?? $type?->label() ?? $section->type }}
                        </span>

                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-white/10 dark:text-gray-300">
                            {{ $type?->label() ?? $section->type }}
                        </span>

                        <span @class([
                            'rounded px-1.5 py-0.5 text-[10px]',
                            'bg-[#7FB69A]/25 text-[#0E2A1C]' => $section->source === 'manual',
                            'bg-gray-200 text-gray-700 dark:bg-white/10 dark:text-gray-300' => $section->source === 'auto',
                            'bg-[#C9A063]/25 text-[#14181A]' => $section->source === 'mixed',
                        ])>
                            {{ ['manual' => 'يدوي', 'auto' => 'تلقائي', 'mixed' => 'مختلط'][$section->source] ?? $section->source }}
                        </span>

                        <span class="text-[11px] text-[#7C848A]">
                            {{ $items->count() }} / {{ $resolved['limit'] ?? '—' }}
                        </span>

                        @if ($items->count() < ($resolved['limit'] ?? 0) && ! ($type?->isStatic()))
                            <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] text-red-700">غير مكتمل</span>
                        @endif

                        <div class="ms-auto flex items-center gap-1">
                            {{-- The drag handles are pointer-only; these are the
                                 same reorder from a keyboard. --}}
                            <button type="button" wire:click="moveSection({{ $section->id }}, -1)"
                                    @disabled($loop->first)
                                    aria-label="نقل {{ $section->title(app()->getLocale()) ?? $section->type }} لأعلى"
                                    class="rounded p-1 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-white/10">
                                <x-filament::icon icon="heroicon-o-arrow-up" class="h-4 w-4 text-gray-500" />
                            </button>
                            <button type="button" wire:click="moveSection({{ $section->id }}, 1)"
                                    @disabled($loop->last)
                                    aria-label="نقل {{ $section->title(app()->getLocale()) ?? $section->type }} لأسفل"
                                    class="rounded p-1 hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-white/10">
                                <x-filament::icon icon="heroicon-o-arrow-down" class="h-4 w-4 text-gray-500" />
                            </button>
                            <button type="button" wire:click="toggleSection({{ $section->id }})"
                                    class="rounded p-1 hover:bg-gray-100 dark:hover:bg-white/10">
                                <x-filament::icon :icon="$section->is_visible ? 'heroicon-o-eye' : 'heroicon-o-eye-slash'"
                                                  class="h-4 w-4 text-gray-500" />
                            </button>
                            <button type="button" wire:click="deleteSection({{ $section->id }})"
                                    wire:confirm="حذف هذا القسم؟"
                                    class="rounded p-1 hover:bg-red-50 dark:hover:bg-red-500/10">
                                <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4 text-red-500" />
                            </button>
                        </div>
                    </div>

                    {{-- The resolved content, not just the rule that produced it. --}}
                    @if ($type?->isStatic())
                        <p class="text-xs text-[#7C848A]">قسم ثابت بلا محتوى ديناميكي.</p>
                    @elseif ($items->isEmpty())
                        <p class="text-xs text-red-600">لا يوجد محتوى مطابق — سيظهر القسم فارغًا للقارئ.</p>
                    @else
                        <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($items as $item)
                                <li class="rounded-lg border border-[#E2DED4] bg-[#F6F4EF] p-2 dark:border-white/10 dark:bg-white/5">
                                    <div class="line-clamp-2 text-xs font-medium text-gray-900 dark:text-gray-100">
                                        {{ $item->title }}
                                    </div>
                                    <div class="mt-1 flex items-center gap-2 text-[10px] text-[#7C848A]">
                                        @if ($item instanceof \App\Models\Article)
                                            <span>{{ $item->category?->name }}</span>
                                            <span dir="ltr" class="masar-ltr">{{ $item->published_at?->format('Y-m-d') }}</span>
                                            @if ($item->hero_media_id === null)
                                                <span class="text-red-600">بلا غلاف</span>
                                            @endif
                                        @else
                                            <span>فرصة</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
