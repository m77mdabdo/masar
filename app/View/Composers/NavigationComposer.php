<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Queries\MenuTreeQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Navigation for every page, resolved once and cached for a day.
 *
 * Menus change a few times a year and render on every request, so this is the
 * clearest win available. The cache is tagged and flushed by
 * FlushNavigationCache the moment an editor saves a menu.
 */
class NavigationComposer
{
    public function __construct(
        private readonly MenuTreeQuery $menus,
    ) {}

    public function compose(View $view): void
    {
        $locale = app()->getLocale();
        $ttl = (int) config('masar.cache.navigation');

        $view->with([
            'headerMenu' => $this->menu('header', $locale, $ttl, 'desktop'),
            'mobileMenu' => $this->menu('mobile', $locale, $ttl, 'mobile'),
            'footerExplore' => $this->menu('footer_explore', $locale, $ttl),
            'footerCompany' => $this->menu('footer_company', $locale, $ttl),
        ]);
    }

    private function menu(string $key, string $locale, int $ttl, ?string $device = null)
    {
        return Cache::tags(['navigation', "navigation:{$key}"])->remember(
            "menu:{$key}:{$locale}:".($device ?? 'any'),
            $ttl,
            fn () => ($this->menus)($key, $locale, $device),
        );
    }
}
