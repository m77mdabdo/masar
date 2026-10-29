<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * An uploaded image is checked and then rebuilt before anything stores it
 * (CLAUDE.md §5: verify the real type, re-encode images).
 *
 * - The file's own first bytes must be a JPEG, PNG or WebP signature: the
 *   extension and the browser's claimed type are not trusted, so a script
 *   renamed photo.jpg is refused here.
 * - Size and pixel dimensions are bounded (config masar.media).
 * - GD must decode it; then it is re-encoded in place, in the same format.
 *   Whatever rode along inside the original — appended code, metadata,
 *   a polyglot payload — does not survive being redrawn from pixels.
 *
 * It runs as validation so it covers plain uploads and media-library uploads
 * alike: both validate before they store.
 */
final class SafeImage implements ValidationRule
{
    private const SIGNATURES = [
        'jpeg' => ["\xFF\xD8\xFF"],
        'png' => ["\x89PNG\r\n\x1A\n"],
        'webp' => ['RIFF'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! is_file((string) $value->getRealPath())) {
            return; // An already-stored file (an unchanged field) is not re-checked.
        }

        $path = (string) $value->getRealPath();

        if ($value->getSize() > (int) config('masar.media.max_upload_kb') * 1024) {
            $fail(__('admin.upload.too_large', ['kb' => (int) config('masar.media.max_upload_kb')]));

            return;
        }

        $type = $this->signature($path);
        if ($type === null) {
            $fail(__('admin.upload.not_an_image'));

            return;
        }

        $size = @getimagesize($path);
        $max = (int) config('masar.media.max_dimension', 8000);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] > $max || $size[1] > $max) {
            $fail(__('admin.upload.dimensions', ['max' => $max]));

            return;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            $fail(__('admin.upload.not_an_image'));

            return;
        }

        $this->reencode($image, $type, $path) || $fail(__('admin.upload.not_an_image'));
        imagedestroy($image);
    }

    private function signature(string $path): ?string
    {
        $head = (string) file_get_contents($path, false, null, 0, 16);

        foreach (self::SIGNATURES as $type => $magics) {
            foreach ($magics as $magic) {
                if (str_starts_with($head, $magic) && ($type !== 'webp' || substr($head, 8, 4) === 'WEBP')) {
                    return $type;
                }
            }
        }

        return null;
    }

    private function reencode(\GdImage $image, string $type, string $path): bool
    {
        $tmp = $path.'.reencoded';

        $ok = match ($type) {
            'jpeg' => imagejpeg($image, $tmp, 90),
            'png' => (imagesavealpha($image, true) && imagepng($image, $tmp, 6)),
            'webp' => imagewebp($image, $tmp, 90),
        };

        return $ok && rename($tmp, $path);
    }
}
