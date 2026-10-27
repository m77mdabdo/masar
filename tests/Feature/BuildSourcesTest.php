<?php

declare(strict_types=1);

/**
 * The public stylesheet is built from markup, not from documentation.
 *
 * Tailwind v4 scans every file git does not ignore unless told otherwise, and
 * for months CLAUDE.md, REPORT.md and the test files contributed class names to
 * the bundle — including a `focus:outline-none` rule the project forbids. The
 * build was not reproducible from the source it claimed to be built from.
 */
it('builds the public stylesheet from declared sources only', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toMatch("/@import\\s+'tailwindcss'\\s+source\\(none\\);/");

    preg_match_all("/@source\\s+'([^']+)';/", $css, $m);

    expect($m[1])->not->toBeEmpty();

    foreach ($m[1] as $source) {
        $path = realpath(resource_path('css/'.$source));

        expect($path)->not->toBeFalse("@source {$source} does not exist")
            ->and(str_starts_with($path, resource_path()) || str_starts_with($path, app_path('View')))
            ->toBeTrue("@source {$source} reaches outside resources/ and app/View");
    }
});
