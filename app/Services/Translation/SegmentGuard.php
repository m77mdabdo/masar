<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Support\Locales;

/**
 * What a provider may not change, and the check that it did not.
 *
 * Before sending, every URL, email address, figure and Latin-script name is
 * swapped for a numbered marker ⟦n⟧. A provider that rewrites `3,196` as
 * `٣١٩٦`, localises a URL or "translates" a ticker cannot do it, because it
 * never sees them. After, every marker must come back exactly once, and the
 * text must be written in the target edition's script. A result that fails
 * either is rejected whole: half a translation is not published as if it
 * were one.
 */
final class SegmentGuard
{
    private const PROTECT = [
        '~https?://\S+~u',
        '~[\w.+-]+@[\w-]+\.[\w.-]+~u',
        // Figures with their separators and units: 3,196 · 30% · 2026-04 · 1.5x
        '~[+-]?\d[\d,.:/%x-]*~u',
    ];

    /**
     * @param  array<string, string>  $segments
     * @return array{0: array<string, string>, 1: array<string, array<int, string>>}
     */
    public function protect(array $segments, string $from): array
    {
        $patterns = self::PROTECT;

        // From a non-Latin source, a Latin run is a name or a ticker
        // (Aramco, PIF, TASI) and goes across unchanged.
        if (Locales::script($from) !== 'latin') {
            $patterns[] = '~\b[A-Za-z][A-Za-z&.\-]*(?:\s+[A-Z][A-Za-z&.\-]*)*\b~u';
        }

        $protected = [];
        $maps = [];

        foreach ($segments as $path => $text) {
            $map = [];
            foreach ($patterns as $pattern) {
                $text = preg_replace_callback($pattern, function (array $m) use (&$map): string {
                    $map[] = $m[0];

                    return '⟦'.(count($map) - 1).'⟧';
                }, $text);
            }
            $protected[$path] = $text;
            $maps[$path] = $map;
        }

        return [$protected, $maps];
    }

    /**
     * @param  array<string, string>  $sent
     * @param  array<string, string>  $received
     * @param  array<string, array<int, string>>  $maps
     * @return array<string, string>
     */
    public function restore(array $sent, array $received, array $maps, string $to): array
    {
        $out = [];

        foreach ($sent as $path => $_) {
            $text = $received[$path] ?? null;

            if (! is_string($text) || trim($text) === '') {
                throw new TranslationRejected("Segment {$path} came back empty.");
            }

            foreach ($maps[$path] as $n => $original) {
                if (substr_count($text, "⟦{$n}⟧") !== 1) {
                    throw new TranslationRejected("Segment {$path} lost or repeated protected token {$n}.");
                }
                $text = str_replace("⟦{$n}⟧", $original, $text);
            }

            if (preg_match('/⟦\d+⟧/u', $text)) {
                throw new TranslationRejected("Segment {$path} invented a protected token.");
            }

            $out[$path] = $text;
        }

        if (! $this->writtenIn(implode(' ', $out), $to)) {
            throw new TranslationRejected("The result is not written in the script of {$to}.");
        }

        return $out;
    }

    /**
     * Most letters must belong to the edition's script. Urdu must also carry
     * a letter Urdu adds to the Arabic alphabet: Arabic text returned for an
     * Urdu edition passes every other check and is still the wrong language.
     */
    public function writtenIn(string $text, string $locale): bool
    {
        $script = Locales::script($locale);
        $class = match ($script) {
            'arabic', 'nastaliq' => '\p{Arabic}',
            'devanagari' => '\p{Devanagari}',
            'han' => '\p{Han}',
            default => '\p{Latin}',
        };

        $letters = preg_match_all('/\p{L}/u', $text);
        $own = preg_match_all('/'.$class.'/u', $text);

        if ($letters === 0 || $own / $letters < 0.5) {
            return false;
        }

        return $script !== 'nastaliq' || preg_match('/[ےہںٹڈڑۓ]/u', $text) === 1;
    }
}
