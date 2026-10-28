<?php

declare(strict_types=1);

use App\Support\Locales;
use Illuminate\Support\Arr;

/*
 * Arabic is the source language of the interface. Every key it defines must
 * exist, non-empty, in all six editions, with the same placeholders: a
 * missing key renders its own name (`site.nav.menu`) to a reader, and a
 * dropped `:title` silently loses the thing the sentence was about.
 *
 * It walks lang/ar on disk rather than a list of groups, so a new group is
 * covered the moment it exists.
 */
function langGroups(): array
{
    return array_map(fn (string $f): string => basename($f, '.php'), glob(lang_path('ar/*.php')));
}

function flatLang(string $code, string $group): array
{
    $file = lang_path("{$code}/{$group}.php");

    return is_file($file) ? Arr::dot(require $file) : [];
}

it('has a file for every group in every edition', function (): void {
    expect(langGroups())->not->toBeEmpty();

    foreach (Locales::codes() as $code) {
        foreach (langGroups() as $group) {
            expect(lang_path("{$code}/{$group}.php"))->toBeFile();
        }
    }
});

it('defines every Arabic key in every edition, non-empty, with the same placeholders', function (): void {
    $problems = [];

    foreach (langGroups() as $group) {
        $source = flatLang('ar', $group);

        foreach (Locales::codes() as $code) {
            $target = flatLang($code, $group);

            foreach ($source as $key => $text) {
                $value = $target[$key] ?? null;

                if (! is_string($value) || trim($value) === '') {
                    $problems[] = "{$code}: {$group}.{$key} missing";

                    continue;
                }

                // A set, not a list: each plural form of `{1} :count story|
                // [0,*] :count stories` repeats the placeholder.
                preg_match_all('/:([a-z_]+)/', $text, $want);
                preg_match_all('/:([a-z_]+)/', $value, $have);
                $want = array_values(array_unique($want[1]));
                $have = array_values(array_unique($have[1]));
                sort($want);
                sort($have);

                if ($want !== $have) {
                    $problems[] = "{$code}: {$group}.{$key} placeholders differ";
                }
            }

            foreach (array_diff_key($target, $source) as $key => $_) {
                $problems[] = "{$code}: {$group}.{$key} has no Arabic source";
            }
        }
    }

    expect($problems)->toBe([]);
});

it('does not leave an edition in another edition’s script', function (): void {
    // A copy-paste of the Arabic file into fr/ would pass the key check above.
    // Latin-script editions must not contain Arabic letters; Hindi must
    // contain Devanagari, Chinese must contain Han, Urdu must contain the
    // letters Urdu adds to the Arabic alphabet (ے ہ ں ٹ ڈ ڑ ۓ).
    // Whole editions, not group by group: a two-string group can be
    // legitimately free of any one script's distinguishing letters.
    $join = fn (string $code): string => implode(' ', array_merge(...array_map(fn (string $g): array => flatLang($code, $g), langGroups())));

    expect(preg_match('/\p{Arabic}/u', $join('en').$join('fr').$join('zh-hans').$join('hi')))->toBe(0)
        ->and(preg_match('/\p{Devanagari}/u', $join('hi')))->toBe(1)
        ->and(preg_match('/\p{Han}/u', $join('zh-hans')))->toBe(1)
        ->and(preg_match('/[ےہںٹڈڑۓ]/u', $join('ur-pk')))->toBe(1);
});

it('renders a translated key rather than its name', function (): void {
    foreach (Locales::codes() as $code) {
        app()->setLocale($code);
        expect(__('site.nav.menu'))->not->toBe('site.nav.menu');
    }
});
