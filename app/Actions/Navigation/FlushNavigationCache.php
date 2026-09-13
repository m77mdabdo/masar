<?php

declare(strict_types=1);

namespace App\Actions\Navigation;

use App\Models\Menu;
use Illuminate\Support\Facades\Cache;

/**
 * Clears the navigation cache the moment a menu changes.
 *
 * Navigation is cached for a day (config masar.cache.navigation) because it is
 * rendered on every page and almost never changes. That trade only works if an
 * edit is visible immediately — an owner who reorders the header and still sees
 * the old one will reorder it again, and then file a bug.
 */
class FlushNavigationCache
{
    public function __invoke(?Menu $menu = null): void
    {
        $tags = ['navigation'];

        if ($menu !== null) {
            $tags[] = 'navigation:'.$menu->key;
        }

        foreach ($tags as $tag) {
            Cache::tags($tag)->flush();
        }
    }
}
