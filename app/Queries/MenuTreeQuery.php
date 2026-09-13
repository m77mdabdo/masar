<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\EntityUrl;
use Illuminate\Support\Collection;

/**
 * Resolves a menu into a render-ready tree.
 *
 * One query for the whole menu, assembled in memory — never a query per level.
 * The public header, the mobile drawer and the admin preview all consume this,
 * so a URL resolved here is resolved identically everywhere.
 */
class MenuTreeQuery
{
    public function __construct(
        private readonly EntityUrl $urls,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function __invoke(
        string $menuKey,
        string $locale,
        ?string $device = null,
        bool $respectSchedule = true,
    ): Collection {
        $menu = Menu::query()->where('key', $menuKey)->first();

        if ($menu === null) {
            return collect();
        }

        $items = MenuItem::query()
            ->where('menu_id', $menu->getKey())
            ->with('linkable')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (MenuItem $item): bool => $this->isVisible($item, $device, $respectSchedule));

        return $this->build($items, null, $locale);
    }

    private function isVisible(MenuItem $item, ?string $device, bool $respectSchedule): bool
    {
        if (! $item->is_active) {
            return false;
        }

        if ($device === 'desktop' && ! $item->show_desktop) {
            return false;
        }

        if ($device === 'mobile' && ! $item->show_mobile) {
            return false;
        }

        if (! $respectSchedule) {
            return true;
        }

        if ($item->starts_at !== null && $item->starts_at->isFuture()) {
            return false;
        }

        return ! ($item->ends_at !== null && $item->ends_at->isPast());
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function build(Collection $items, ?int $parentId, string $locale): Collection
    {
        return $items
            ->filter(fn (MenuItem $item): bool => (int) $item->parent_id === (int) $parentId)
            ->values()
            ->map(fn (MenuItem $item): array => [
                'id' => $item->getKey(),
                'label' => $item->label($locale),
                'url' => $this->resolveUrl($item, $locale),
                'is_mega' => (bool) $item->is_mega,
                'column_group' => $item->column_group,
                'children' => $this->build($items, $item->getKey(), $locale),
            ]);
    }

    /**
     * An entity link is rebuilt from the entity's current slug on every render,
     * which is why it survives a rename. A manual URL is returned verbatim and
     * rots the moment its target moves.
     *
     * The URL itself comes from EntityUrl — this class no longer knows the shape
     * of a MASAR path, and cannot drift from the router or from redirect
     * validation.
     */
    private function resolveUrl(MenuItem $item, string $locale): ?string
    {
        if ($item->linkable_type === null) {
            return $item->url;
        }

        $linkable = $item->linkable;

        if ($linkable === null) {
            return $item->url;
        }

        return $this->urls->for($linkable, $locale) ?? $item->url;
    }
}
