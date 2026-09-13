<x-filament-panels::page>
    @php
        $menus = $this->menus();
        $tree = $this->tree();
        $preview = $this->preview();
    @endphp

    {{-- Menu switcher --}}
    <div class="flex flex-wrap gap-2">
        @foreach ($menus as $menu)
            <button
                type="button"
                wire:click="selectMenu('{{ $menu->key }}')"
                @class([
                    'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                    'bg-[#0E2A1C] text-white' => $this->menuKey === $menu->key,
                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300' => $this->menuKey !== $menu->key,
                ])
            >
                {{ $menu->name }}
                <span class="ms-1 opacity-60">({{ $menu->items()->count() }})</span>
            </button>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Editor --}}
        <div class="lg:col-span-2">
            <div
                class="fi-section rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900"
                x-data="masarMenuTree(@js($tree))"
            >
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                    اسحب لإعادة الترتيب أو لتغيير الأب. الحد الأقصى {{ \App\Actions\Navigation\ValidateMenuTree::MAX_DEPTH }} مستويات.
                </p>

                @if ($tree === [])
                    <p class="py-8 text-center text-sm text-gray-400">لا توجد عناصر في هذه القائمة بعد.</p>
                @else
                    <ul class="space-y-1" x-ref="root">
                        @include('filament.pages.partials.menu-branch', ['nodes' => $tree, 'depth' => 1])
                    </ul>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            x-on:click="save()"
                            x-bind:disabled="! dirty"
                            class="rounded-lg bg-[#0E2A1C] px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                        >
                            حفظ الترتيب
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Live preview: the same query object the public header uses. --}}
        <div>
            <div class="fi-section rounded-xl border border-gray-200 bg-[#F6F4EF] p-4 dark:border-white/10 dark:bg-gray-900">
                <p class="mb-3 text-xs font-medium text-gray-500 dark:text-gray-400">
                    معاينة مباشرة — كما سيراها القارئ
                </p>

                @if ($preview === [])
                    <p class="py-6 text-center text-xs text-gray-400">لا شيء ظاهر للقارئ حاليًا.</p>
                @else
                    <nav dir="rtl">
                        <ul class="space-y-2">
                            @foreach ($preview as $node)
                                <li>
                                    <span class="text-sm font-medium text-[#0E2A1C] dark:text-gray-100">
                                        {{ $node['label'] }}
                                    </span>
                                    @if ($node['is_mega'])
                                        <span class="ms-1 rounded bg-[#7FB69A]/25 px-1.5 py-0.5 text-[10px]">ضخمة</span>
                                    @endif
                                    <div class="masar-ltr text-[11px] text-[#7C848A]" dir="ltr">{{ $node['url'] ?? '—' }}</div>

                                    @if (count($node['children']))
                                        <ul class="ms-4 mt-1 space-y-1 border-s border-[#E2DED4] ps-3">
                                            @foreach ($node['children'] as $child)
                                                <li>
                                                    <span class="text-xs text-gray-700 dark:text-gray-300">{{ $child['label'] }}</span>
                                                    <div class="masar-ltr text-[11px] text-[#7C848A]" dir="ltr">{{ $child['url'] ?? '—' }}</div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('masarMenuTree', (initial) => ({
            tree: initial,
            dirty: false,
            dragging: null,

            start(id) { this.dragging = id },
            end() { this.dragging = null },

            /* Structure is validated server-side too; this only keeps the UI honest. */
            dropOn(targetId, position) {
                if (! this.dragging || this.dragging === targetId) return
                const moved = this.extract(this.tree, this.dragging)
                if (! moved) return
                if (this.contains(moved.node, targetId)) {
                    this.tree = initial
                    return
                }
                this.insert(this.tree, targetId, position, moved.node)
                this.dirty = true
                this.end()
            },

            extract(nodes, id) {
                for (let i = 0; i < nodes.length; i++) {
                    if (nodes[i].id === id) return { node: nodes.splice(i, 1)[0] }
                    const found = this.extract(nodes[i].children ?? [], id)
                    if (found) return found
                }
                return null
            },

            contains(node, id) {
                if (node.id === id) return true
                return (node.children ?? []).some(c => this.contains(c, id))
            },

            insert(nodes, targetId, position, node) {
                for (let i = 0; i < nodes.length; i++) {
                    if (nodes[i].id === targetId) {
                        if (position === 'inside') {
                            nodes[i].children = nodes[i].children ?? []
                            nodes[i].children.push(node)
                        } else {
                            nodes.splice(position === 'before' ? i : i + 1, 0, node)
                        }
                        return true
                    }
                    if (this.insert(nodes[i].children ?? [], targetId, position, node)) return true
                }
                return false
            },

            save() {
                $wire.saveTree(this.strip(this.tree))
                this.dirty = false
            },

            /* Only ids and shape reach the server; labels and flags are not the tree's business. */
            strip(nodes) {
                return nodes.map(n => ({ id: n.id, children: this.strip(n.children ?? []) }))
            },
        }))
    </script>
    @endscript
</x-filament-panels::page>
