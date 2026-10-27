<?php

declare(strict_types=1);

namespace App\Actions\Navigation;

use App\Exceptions\InvalidMenuTree;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
     * @throws AuthorizationException
     */
    public function __invoke(Menu $menu, array $tree, User $actor): void
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

        $items = $menu->items()->get()->keyBy('id');

        foreach ($flat as $row) {
            Gate::forUser($actor)->authorize('update', $items[$row['id']]);
        }

        // Through the model, not a bulk update: a query-builder update fires
        // no model events, so every reorder of the site's navigation used to
        // leave no audit entry. Unchanged rows are not dirty and log nothing.
        DB::transaction(function () use ($flat, $items): void {
            foreach ($flat as $row) {
                $items[$row['id']]->forceFill([
                    'parent_id' => $row['parent_id'],
                    'sort_order' => $row['sort_order'],
                ])->save();
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
