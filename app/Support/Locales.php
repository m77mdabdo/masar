<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The six editions, read from `config('masar.locales')` in one place.
 *
 * Every question the code asks about a locale — is it real, is it live, what
 * does it write in `<html lang>`, which way does it run — has one answer here.
 * Several callers used to read the config array directly and each restated
 * "enabled" slightly differently; a rule with more than one implementation has
 * none.
 */
final class Locales
{
    /**
     * @return array<string, array{name: string, tag: string, dir: string, script: string, enabled: bool}>
     */
    public static function all(): array
    {
        return (array) config('masar.locales');
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /** @return list<string> */
    public static function enabled(): array
    {
        return array_keys(array_filter(self::all(), fn (array $l): bool => (bool) ($l['enabled'] ?? false)));
    }

    public static function exists(string $code): bool
    {
        return array_key_exists($code, self::all());
    }

    public static function isEnabled(string $code): bool
    {
        return in_array($code, self::enabled(), true);
    }

    public static function default(): string
    {
        return (string) config('masar.default_locale', 'ar');
    }

    /** BCP 47, for `<html lang>`, hreflang and `og:locale`. */
    public static function tag(string $code): string
    {
        return (string) (self::all()[$code]['tag'] ?? $code);
    }

    public static function dir(string $code): string
    {
        return (string) (self::all()[$code]['dir'] ?? 'rtl');
    }

    public static function script(string $code): string
    {
        return (string) (self::all()[$code]['script'] ?? 'arabic');
    }

    public static function name(string $code): string
    {
        return (string) (self::all()[$code]['name'] ?? $code);
    }

    /**
     * The code for a BCP 47 tag in any case or separator — `ur_PK`, `UR-pk`,
     * `zh-Hans` — or null. Accept-Language and Symfony's normalised form both
     * arrive here.
     */
    public static function fromTag(string $tag): ?string
    {
        $wanted = strtolower(str_replace('_', '-', $tag));

        foreach (self::all() as $code => $locale) {
            if (strtolower($locale['tag']) === $wanted || $code === $wanted) {
                return $code;
            }
        }

        return null;
    }
}
