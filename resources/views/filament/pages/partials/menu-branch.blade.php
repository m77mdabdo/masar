@foreach ($nodes as $node)
    <li wire:key="node-{{ $node['id'] }}">
        <div
            draggable="true"
            x-on:dragstart="start({{ $node['id'] }})"
            x-on:dragend="end()"
            x-on:dragover.prevent
            x-on:drop.prevent="dropOn({{ $node['id'] }}, $event.altKey ? 'inside' : 'after')"
            @class([
                'flex items-center gap-2 rounded-lg border px-3 py-2 cursor-grab active:cursor-grabbing',
                'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900' => $node['is_active'],
                'border-dashed border-gray-300 bg-gray-50 opacity-70 dark:bg-white/5' => ! $node['is_active'],
            ])
        >
            <x-filament::icon icon="heroicon-o-bars-2" class="h-4 w-4 shrink-0 text-gray-400" />

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $node['label'] }}</span>

                    @if ($node['is_entity_link'])
                        <span class="rounded bg-[#7FB69A]/25 px-1.5 py-0.5 text-[10px] text-[#0E2A1C]" title="يتتبع تغيّر المسار">كيان</span>
                    @else
                        <span class="rounded bg-[#C9A063]/25 px-1.5 py-0.5 text-[10px] text-[#14181A]" title="لا يتتبع تغيّر المسار">رابط يدوي</span>
                    @endif

                    @if ($node['is_mega'])
                        <span class="rounded bg-gray-200 px-1.5 py-0.5 text-[10px] dark:bg-white/10">ضخمة</span>
                    @endif

                    @if ($node['scheduled'])
                        <x-filament::icon icon="heroicon-o-clock" class="h-3.5 w-3.5 text-[#C9A063]" />
                    @endif
                </div>

                <div class="truncate text-[11px] text-[#7C848A]">{{ $node['target'] }}</div>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                @unless ($node['show_desktop'])
                    <x-filament::icon icon="heroicon-o-computer-desktop" class="h-3.5 w-3.5 text-gray-300" title="مخفي على سطح المكتب" />
                @endunless
                @unless ($node['show_mobile'])
                    <x-filament::icon icon="heroicon-o-device-phone-mobile" class="h-3.5 w-3.5 text-gray-300" title="مخفي على الجوال" />
                @endunless

                <button type="button" wire:click="toggleItem({{ $node['id'] }})" class="rounded p-1 hover:bg-gray-100 dark:hover:bg-white/10">
                    <x-filament::icon :icon="$node['is_active'] ? 'heroicon-o-eye' : 'heroicon-o-eye-slash'" class="h-4 w-4 text-gray-500" />
                </button>

                <button type="button" wire:click="deleteItem({{ $node['id'] }})"
                        wire:confirm="حذف هذا العنصر وكل ما تحته؟"
                        class="rounded p-1 hover:bg-red-50 dark:hover:bg-red-500/10">
                    <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4 text-red-500" />
                </button>
            </div>
        </div>

        @if (count($node['children']) && $depth < \App\Actions\Navigation\ValidateMenuTree::MAX_DEPTH)
            <ul class="ms-6 mt-1 space-y-1 border-s border-[#E2DED4] ps-3 dark:border-white/10">
                @include('filament.pages.partials.menu-branch', ['nodes' => $node['children'], 'depth' => $depth + 1])
            </ul>
        @endif
    </li>
@endforeach
