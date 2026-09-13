<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The one place that knows what an image variant is called and how wide it is.
 *
 * Every conversion is generated twice: WebP for browsers that accept it, JPEG
 * for the ones that don't and for social scrapers, which are still the least
 * capable clients we serve. The widths come from config so the set can change
 * without touching a model — the format pairing does not, because a <picture>
 * with only one format is not a fallback.
 */
final class MediaConversions
{
    /** Suffix that marks the JPEG twin of a conversion. */
    public const FALLBACK_SUFFIX = '_jpg';

    /** WebP quality. Paired with the JPEG below so the two stay comparable. */
    public const WEBP_QUALITY = 82;

    public const JPEG_QUALITY = 80;

    /**
     * Set while a bulk importer is generating conversions itself.
     *
     * The demo library puts the same 28 photographs behind 88 articles. Letting
     * the library convert each one per article means 704 decodes of the same
     * handful of frames — ten minutes of `migrate:fresh --seed`. The importer
     * converts each source once and copies the result, and suspends
     * registration so nothing regenerates behind it.
     */
    private static bool $suspended = false;

    /**
     * Run a callback with conversion generation suspended. The caller becomes
     * responsible for writing the files and for marking them generated; a
     * caller that forgets leaves records whose conversions silently 404, so
     * this is for importers, never for request handling.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutGenerating(callable $callback): mixed
    {
        self::$suspended = true;

        try {
            return $callback();
        } finally {
            self::$suspended = false;
        }
    }

    /**
     * Register the WebP/JPEG pair for every configured width on a media-owning
     * model. Called from registerMediaConversions().
     */
    public static function register(HasMedia $model, string ...$collections): void
    {
        if (self::$suspended) {
            return;
        }

        foreach (self::widths() as $name => $width) {
            $webp = $model->addMediaConversion($name)
                ->width($width)
                ->format('webp')
                ->quality(self::WEBP_QUALITY)
                ->nonQueued();

            $jpeg = $model->addMediaConversion($name.self::FALLBACK_SUFFIX)
                ->width($width)
                ->format('jpg')
                ->quality(self::JPEG_QUALITY)
                ->nonQueued();

            // Named explicitly so an image conversion is never attempted on a
            // collection holding MP4s — GD cannot open one, and the failure
            // surfaces as a broken upload rather than as a clear error.
            if ($collections !== []) {
                $webp->performOnCollections(...$collections);
                $jpeg->performOnCollections(...$collections);
            }
        }
    }

    /**
     * @return array<string, int> conversion name => target width in pixels
     */
    public static function widths(): array
    {
        /** @var array<string, int> $widths */
        $widths = config('masar.media.conversions', []);

        return $widths;
    }

    /**
     * The candidate widths a given box should offer.
     *
     * `og` is never a candidate: it exists for social cards, and offering it
     * would let the browser pick a crop nobody designed for the page.
     *
     * The scale matters as much. A 74px rail thumbnail offered an 1800px
     * candidate will take it on a high-density screen, and the markup cost of
     * listing it is paid on every card whether or not it is chosen.
     *
     * @return array<string, int>
     */
    public static function srcsetWidths(string $scale = 'full'): array
    {
        $widths = array_diff_key(self::widths(), ['og' => true]);

        return match ($scale) {
            'thumb' => array_intersect_key($widths, ['small' => true, 'thumb' => true]),
            'card' => array_intersect_key($widths, ['thumb' => true, 'medium' => true]),
            default => $widths,
        };
    }

    /**
     * Every conversion name, both formats, in the order they are generated.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (array_keys(self::widths()) as $name) {
            $names[] = $name;
            $names[] = $name.self::FALLBACK_SUFFIX;
        }

        return $names;
    }

    /**
     * The file name Media Library gives a conversion: the original's name, a
     * dash, the conversion name, and the conversion's own extension.
     */
    public static function conversionFileName(string $originalFileName, string $conversion): string
    {
        $stem = pathinfo($originalFileName, PATHINFO_FILENAME);
        $extension = str_ends_with($conversion, self::FALLBACK_SUFFIX) ? 'jpg' : 'webp';

        return "{$stem}-{$conversion}.{$extension}";
    }

    /**
     * A srcset string for one format, skipping any conversion that was never
     * generated — a half-converted record should degrade to fewer candidates,
     * not to a 404 the browser will happily download.
     */
    public static function srcset(Media $media, bool $webp = true, string $scale = 'full'): string
    {
        $parts = [];

        foreach (self::srcsetWidths($scale) as $name => $width) {
            $conversion = $webp ? $name : $name.self::FALLBACK_SUFFIX;

            if (! $media->hasGeneratedConversion($conversion)) {
                continue;
            }

            $parts[] = $media->getUrl($conversion).' '.$width.'w';
        }

        return implode(', ', $parts);
    }

    /**
     * The <img src>: the JPEG mid-size if it exists, otherwise the original.
     * Never a WebP, because src is what a browser without <picture> support
     * reads.
     */
    public static function fallbackSrc(Media $media): string
    {
        foreach (['medium', 'large', 'thumb', 'small'] as $name) {
            if ($media->hasGeneratedConversion($name.self::FALLBACK_SUFFIX)) {
                return $media->getUrl($name.self::FALLBACK_SUFFIX);
            }
        }

        return $media->getUrl();
    }

    /**
     * The social card image. JPEG on purpose: several scrapers still reject
     * WebP and fall back to no image at all rather than to the original.
     */
    public static function socialSrc(Media $media): string
    {
        return $media->hasGeneratedConversion('og'.self::FALLBACK_SUFFIX)
            ? $media->getUrl('og'.self::FALLBACK_SUFFIX)
            : $media->getUrl();
    }
}
