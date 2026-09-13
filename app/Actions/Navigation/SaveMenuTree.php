<?php

declare(strict_types=1);

namespace App\Actions\Navigation;

use App\Exceptions\InvalidMenuTree;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

/**
 * Persists a reordered/reparented menu tree in one transaction.
 *
 * Validates first and writes nothing on failure: a menu half-saved into a
 * cyclic state takes the public header down, and "it was mid-save" is not a
 * state the site should ever be able to reach.
 */
class SaveMenuTree
{
    public function __construct(
        private readonly ValidateMenuTree $validate,
        private readonly FlushNavigationCache $flush,
    ) {}

    /**
     * @param  array<int, array{id: int|string, children?: array<int, mixed>}>  $tree
     *
     * @throws InvalidMenuTree
     */
    public function __invoke(Menu $menu, array $tree): void
    {
        $problems = ($this->validate)($tree);

        if ($problems !== []) {
            throw new InvalidMenuTree($problems);
        }

        $ownedIds = $menu->items()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $flat = $this->flatten($tree, null);

        // Every id in the payload must already belong to this menu. Without
        // this, a crafted request could adopt another menu's items.
        foreach ($flat as $row) {
            if (! in_array($row['id'], $ownedIds, true)) {
                throw new InvalidMenuTree(['عنصر لا ينتمي إلى هذه القائمة.']);
            }
        }

        DB::transaction(function () use ($flat): void {
            foreach ($flat as $row) {
                MenuItem::query()
                    ->whereKey($row['id'])
                    ->update([
                        'parent_id' => $row['parent_id'],
                        'sort_order' => $row['sort_order'],
                    ]);
            }
        });

        ($this->flush)($menu);
    }

    /**
     * Depth-first flatten, recording each node's parent and position.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array{id: int, parent_id: int|null, sort_order: int}>
     */
    private function flatten(array $nodes, ?int $parentId): array
    {
        $rows = [];
        $position = 1;

        foreach ($nodes as $node) {
            $id = (int) $node['id'];

            $rows[] = [
                'id' => $id,
                'parent_id' => $parentId,
                'sort_order' => $position++,
            ];

            $children = $node['children'] ?? [];

            if (is_array($children) && $children !== []) {
                $rows = [...$rows, ...$this->flatten($children, $id)];
            }
        }

        return $rows;
    }
}
