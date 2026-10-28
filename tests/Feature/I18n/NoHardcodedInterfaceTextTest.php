<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Interface text lives in lang/, never in a public template. The walk is over
 * every Blade file on disk outside the admin panel's views, not a list, so a
 * new template is covered the day it is added.
 *
 * Arabic letters in markup are the signal: Arabic is the source language, so
 * a string typed straight into a view is almost always Arabic. Comments are
 * exempt (they explain editorial logic, in Arabic where that reads better —
 * CLAUDE.md §6), and so is the brand lockup, which is the name «مسار» in
 * every edition.
 */
function publicTemplates(): Finder
{
    return Finder::create()->files()->in(resource_path('views'))->name('*.blade.php')->exclude('filament');
}

function stripComments(string $blade): string
{
    $blade = preg_replace('/\{\{--.*?--\}\}/s', '', $blade);
    $blade = preg_replace('#/\*.*?\*/#s', '', $blade);

    return preg_replace('#^\s*//.*$#m', '', $blade);
}

it('keeps Arabic interface text out of every public template', function (): void {
    $found = [];

    foreach (publicTemplates() as $file) {
        $markup = str_replace('>مسار</span>', '></span>', stripComments($file->getContents()));

        foreach (explode("\n", $markup) as $line) {
            if (preg_match('/\p{Arabic}/u', $line)) {
                $found[] = $file->getRelativePathname().': '.trim($line);
            }
        }
    }

    expect($found)->toBe([]);
});

it('defines every translation key a public template asks for', function (): void {
    // A typo in a key renders the key itself to the reader; this reads every
    // literal key out of the templates and resolves it in the source language.
    app()->setLocale('ar');
    $missing = [];

    foreach (publicTemplates() as $file) {
        preg_match_all("/(?:__|trans_choice)\\('([a-z_]+\\.[a-z0-9_.]+)'/", $file->getContents(), $keys);

        foreach (array_unique($keys[1]) as $key) {
            if (! \Illuminate\Support\Facades\Lang::has($key, 'ar', false)) {
                $missing[] = $file->getRelativePathname().': '.$key;
            }
        }
    }

    expect($missing)->toBe([]);
});
