<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Translation\FileLoader;

/**
 * Lets packages find their own translations under our edition codes.
 *
 * Our codes are URL segments (`ur-pk`, `zh-hans`); Filament and Laravel ship
 * theirs as `ur` and `zh_CN`. For a package namespace only, a lookup that
 * finds nothing under our code is retried under the package's. Our own groups
 * (namespace `*`) are never remapped: lang/ur-pk is the only Urdu we use.
 */
final class EditionTranslationLoader extends FileLoader
{
    private const VENDOR = ['ur-pk' => 'ur', 'zh-hans' => 'zh_CN'];

    public function load($locale, $group, $namespace = null): array
    {
        $lines = parent::load($locale, $group, $namespace);

        if ($lines === [] && $namespace !== null && $namespace !== '*' && isset(self::VENDOR[$locale])) {
            return parent::load(self::VENDOR[$locale], $group, $namespace);
        }

        return $lines;
    }
}
