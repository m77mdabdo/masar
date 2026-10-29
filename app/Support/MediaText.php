<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A media record's reader-facing text in the page's edition. One photograph
 * serves every edition, so its alt text and caption carry a per-edition map
 * (`alt_translations`) beside the Arabic original.
 */
final class MediaText
{
    public static function alt(?Media $media, ?string $fallback = null): string
    {
        if ($media === null) {
            return (string) $fallback;
        }

        $map = (array) $media->getCustomProperty('alt_translations', []);

        return (string) ($map[app()->getLocale()] ?? $media->getCustomProperty('alt') ?? $fallback);
    }

    public static function credit(?Media $media, ?string $fallback = null): ?string
    {
        if ($media === null) {
            return $fallback;
        }

        $map = (array) $media->getCustomProperty('credit_translations', []);

        return $map[app()->getLocale()] ?? $media->getCustomProperty('credit') ?? $fallback;
    }
}
