<?php

declare(strict_types=1);

namespace App\Actions\Redirects;

/**
 * One canonical spelling for every path.
 *
 * `/ar/saudi`, `ar/saudi/`, `/ar/saudi?utm_source=x` and `https://masar.sa/ar/saudi`
 * are the same destination to a reader and must be the same row here, or the
 * unique index is decorative and a redirect silently fails to match.
 */
class NormalisePath
{
    public function __invoke(?string $path): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return '/';
        }

        // A pasted full URL keeps only its path.
        if (str_contains($path, '://')) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
        }

        // Query strings and fragments are not part of the identity of the page.
        $path = explode('?', $path)[0];
        $path = explode('#', $path)[0];

        $path = '/'.ltrim($path, '/');

        // Collapse duplicate slashes, then drop the trailing one (except root).
        $path = (string) preg_replace('#/+#', '/', $path);

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : rawurldecode($path);
    }
}
