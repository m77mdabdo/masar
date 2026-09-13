<?php

declare(strict_types=1);

namespace App\Actions\Navigation;

/**
 * Structural validation for a menu tree, before anything is written.
 *
 * This runs server-side because the public header is rendered from this data on
 * every page. A tree saved with a cycle does not produce a broken menu — it
 * produces an infinite loop in the renderer, and the site goes down. The UI
 * prevents these shapes; this guarantees them.
 */
class ValidateMenuTree
{
    public const MAX_DEPTH = 3;

    /**
     * @param  array<int, array{id?: mixed, children?: array<int, mixed>}>  $tree
     * @return array<int, string> empty means valid
     */
    public function __invoke(array $tree): array
    {
        $problems = [];
        $seen = [];

        $this->walk($tree, 1, $problems, $seen, []);

        return array_values(array_unique($problems));
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, string>  $problems
     * @param  array<int|string, true>  $seen
     * @param  array<int, int|string>  $ancestors
     */
    private function walk(array $nodes, int $depth, array &$problems, array &$seen, array $ancestors): void
    {
        if ($depth > self::MAX_DEPTH) {
            $problems[] = sprintf('العمق الأقصى للقائمة %d مستويات.', self::MAX_DEPTH);

            return;
        }

        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;

            if ($id === null) {
                $problems[] = 'عنصر بلا معرّف في الشجرة.';

                continue;
            }

            // Ancestry first: a node inside its own subtree is a cycle, and
            // "duplicate" would be a technically-true but useless thing to tell
            // the person who just dragged a parent into its own child.
            if (in_array($id, $ancestors, true)) {
                $problems[] = 'لا يمكن أن يكون العنصر أبًا لنفسه.';

                continue;
            }

            // Otherwise a repeated id means the tree is a graph, and a renderer
            // walking it either duplicates the item or never terminates.
            if (isset($seen[$id])) {
                $problems[] = 'عنصر مكرر في الشجرة — لا يمكن أن يظهر العنصر في مكانين.';

                continue;
            }

            $seen[$id] = true;

            $children = $node['children'] ?? [];

            if (is_array($children) && $children !== []) {
                $this->walk($children, $depth + 1, $problems, $seen, [...$ancestors, $id]);
            }
        }
    }
}
