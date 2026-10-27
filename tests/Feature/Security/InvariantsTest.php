<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Standing security properties, asserted rather than remembered. Each one here
 * is a rule CLAUDE.md states and nothing previously enforced.
 */
it('never leaves a model open to mass assignment', function (): void {
    $open = [];

    foreach (File::files(app_path('Models')) as $file) {
        $class = 'App\\Models\\'.$file->getFilenameWithoutExtension();

        if (! class_exists($class)) {
            continue;
        }

        $source = $file->getContents();

        if (preg_match('/protected \$guarded\s*=\s*\[\s*\]/', $source)) {
            $open[] = $file->getFilename().' declares $guarded = []';

            continue;
        }

        /*
         * App\Models\Media is the one model with no $fillable of its own, and
         * it is deliberate: it extends Spatie's Media, which declares
         * $guarded = [] and writes a dozen columns through its own API. Adding
         * a $fillable list here would break the package rather than protect
         * anything — no media row is ever created from request input; every one
         * comes through addMedia(). Recorded as an exception rather than left
         * as an apparent violation of the rule.
         */
        if ($class === App\Models\Media::class) {
            continue;
        }

        if (! str_contains($source, 'protected $fillable')) {
            $open[] = $file->getFilename().' has no $fillable';
        }
    }

    expect($open)->toBe([], implode('; ', $open));
});

it('encodes every JSON-LD block through the one helper', function (): void {
    $raw = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        $contents = $file->getContents();

        if (! str_contains($contents, 'application/ld+json')) {
            continue;
        }

        // json_encode() in a schema component means someone chose their own
        // flags, which is how the breakout got there the first time.
        if (str_contains($contents, 'json_encode(')) {
            $raw[] = $file->getRelativePathname();
        }
    }

    expect($raw)->toBe([], 'JSON-LD encoded without App\Support\JsonLd: '.implode(', ', $raw));
});

it('constrains the locale parameter at the router', function (): void {
    $route = collect(app('router')->getRoutes())
        ->first(fn ($r): bool => $r->getName() === 'web.home');

    expect($route->wheres)->toHaveKey('locale')
        // An unconstrained {locale} swallows sitemap.xml, rss.xml and every
        // sibling route — CLAUDE.md §6.
        ->and($route->wheres['locale'])->toContain('ar');
});

it('keeps every secret out of the repository', function (): void {
    $tracked = array_filter(explode("\n", (string) shell_exec('cd '.escapeshellarg(base_path()).' && git ls-files 2>/dev/null')));

    $suspicious = array_values(array_filter(
        $tracked,
        fn (string $path): bool => (bool) preg_match('#(^|/)\.env$|\.env\.(local|production|staging)$|\.pem$|\.p12$|id_rsa#', $path),
    ));

    expect($suspicious)->toBe([], 'Tracked secrets: '.implode(', ', $suspicious));
});
