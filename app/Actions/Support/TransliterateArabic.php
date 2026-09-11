<?php

declare(strict_types=1);

namespace App\Actions\Support;

use Illuminate\Support\Str;

/**
 * Arabic → URL-safe Latin.
 *
 * A MASAR slug is an editorial decision, so this Action only ever produces a
 * *suggestion*. It exists because `Str::slug()` returns an empty string for
 * Arabic input, and a URL-encoded Arabic slug (%D8%A7%D9%84...) is unreadable
 * in a share sheet, a search result and an analytics report alike.
 *
 * The output is deliberately lossy. It is a readable handle, not a reversible
 * transliteration scheme — nobody needs to reconstruct the headline from a URL.
 */
class TransliterateArabic
{
    /**
     * Harakat, tanwin, shadda, sukun, superscript alef, and tatweel.
     */
    private const DIACRITICS = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}\x{0640}]/u';

    /**
     * Words that carry no meaning in a URL. Dropping them is what turns
     * "artfaa-hajm-altdawl-fi-alsuq-alraysyh" into something a human can scan.
     *
     * @var array<int, string>
     */
    private const STOP_WORDS = [
        'في', 'من', 'على', 'عن', 'إلى', 'الى', 'مع', 'هذا', 'هذه',
        'التي', 'الذي', 'بين', 'بعد', 'قبل',
    ];

    /**
     * @var array<string, string>
     */
    private const LETTERS = [
        // Hamza carriers normalise to bare alef; the hamza itself is dropped.
        'أ' => 'a', 'إ' => 'a', 'آ' => 'a', 'ٱ' => 'a', 'ا' => 'a',
        'ء' => '', 'ؤ' => '', 'ئ' => '',
        'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h',
        'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z',
        'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't',
        'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
        'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h',
        'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'h',
        // Persian/Urdu forms that turn up in pasted copy.
        'پ' => 'p', 'چ' => 'ch', 'ژ' => 'zh', 'گ' => 'g', 'ک' => 'k', 'ی' => 'y',
        // Arabic-Indic digits → Western, per the numerals rule.
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '٫' => '.', '٬' => '',
    ];

    public function __invoke(string $text, int $maxLength = 60): string
    {
        $text = $this->normalise($text);

        if ($text === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $slugWords = [];

        foreach ($words as $word) {
            $word = $this->stripDefiniteArticle($word);

            if ($word === '' || $this->isStopWord($word)) {
                continue;
            }

            $latin = $this->toLatin($word);

            if ($latin !== '') {
                $slugWords[] = $latin;
            }
        }

        if ($slugWords === []) {
            return '';
        }

        return $this->trimToWordBoundary(implode('-', $slugWords), $maxLength);
    }

    /**
     * Strip diacritics and tatweel, unify Arabic punctuation to spaces, and
     * collapse whitespace.
     */
    private function normalise(string $text): string
    {
        $text = preg_replace(self::DIACRITICS, '', $text) ?? $text;

        // Arabic comma, semicolon, question mark, full stop, quotes, brackets.
        $text = preg_replace('/[\x{060C}\x{061B}\x{061F}\x{06D4}\x{00AB}\x{00BB}\x{201C}\x{201D}]/u', ' ', $text) ?? $text;

        // Any remaining punctuation becomes a word break rather than vanishing,
        // so "الرياض/جدة" does not become one run-on word.
        $text = preg_replace('/[^\p{Arabic}\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Remove a leading definite article, but only when a real word remains —
     * "الله" must not become "ه".
     */
    private function stripDefiniteArticle(string $word): string
    {
        if (mb_strlen($word) > 3 && str_starts_with($word, 'ال')) {
            return mb_substr($word, 2);
        }

        return $word;
    }

    private function isStopWord(string $word): bool
    {
        return in_array($word, self::STOP_WORDS, true);
    }

    /**
     * Latin and digits pass through untouched; Arabic letters map through the
     * table. Mixed-script words ("شركة Aramco") therefore survive intact.
     */
    private function toLatin(string $word): string
    {
        $out = '';

        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $out .= self::LETTERS[$char] ?? $char;
        }

        return Str::slug($out);
    }

    /**
     * Trim to the length limit without cutting a word in half.
     */
    private function trimToWordBoundary(string $slug, int $maxLength): string
    {
        if ($maxLength <= 0 || mb_strlen($slug) <= $maxLength) {
            return trim($slug, '-');
        }

        $cut = mb_substr($slug, 0, $maxLength);
        $lastHyphen = mb_strrpos($cut, '-');

        if ($lastHyphen !== false && $lastHyphen > 0) {
            $cut = mb_substr($cut, 0, $lastHyphen);
        }

        return trim($cut, '-');
    }
}
