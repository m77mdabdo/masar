<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Folds Arabic text to a single comparable form for search.
 *
 * Arabic readers type the same word many ways. "الاستثمار", "إستثمار" and
 * "استثمار" are one query to a human and three to a naive index, so both the
 * stored text and the query are folded through this before they meet.
 *
 * Deliberately aggressive and lossy: it exists to make matching generous, not to
 * preserve meaning. Never use it for anything a reader will see.
 */
class ArabicNormaliser
{
    /**
     * Harakat, tanwin, shadda, sukun, superscript alef, and tatweel.
     */
    private const DIACRITICS = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';

    /**
     * @var array<string, string>
     */
    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي',
        'ؤ' => 'و',
        'ئ' => 'ي',
        'ء' => '',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public function __invoke(?string $text): string
    {
        $text = (string) $text;

        if ($text === '') {
            return '';
        }

        $text = preg_replace(self::DIACRITICS, '', $text) ?? $text;
        $text = strtr($text, self::LETTERS);
        $text = mb_strtolower($text);

        $words = preg_split('/[^\p{Arabic}\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $folded = array_map(fn (string $word): string => $this->stripArticle($word), $words);

        return implode(' ', array_filter($folded));
    }

    /**
     * Drop the definite article so "الاستثمار" and "استثمار" fold together —
     * but only when a real word survives, or "الله" would become "ه".
     */
    private function stripArticle(string $word): string
    {
        if (mb_strlen($word) > 3 && str_starts_with($word, 'ال')) {
            return mb_substr($word, 2);
        }

        return $word;
    }
}
