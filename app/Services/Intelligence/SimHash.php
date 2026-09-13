<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Support\ArabicNormaliser;

/**
 * A 64-bit fingerprint of a headline, and the distance between two of them.
 *
 * Stage four of deduplication, and the only stage that is a judgement rather
 * than an identity. SimHash was chosen over shingling or embeddings because it
 * is one integer per item, comparable with an XOR, and needs no service: the
 * whole point of the four-stage ladder is that it gets more expensive only when
 * the cheap answers have failed.
 *
 * Arabic is folded through the same normaliser the search uses, so a headline
 * with and without diacritics fingerprints identically.
 */
final class SimHash
{
    private const BITS = 64;

    public function __construct(private readonly ArabicNormaliser $normaliser) {}

    /** @return string 16 hex characters, or null for text with nothing in it */
    public function __invoke(?string $text): ?string
    {
        $tokens = $this->tokens($text);

        if ($tokens === []) {
            return null;
        }

        $vector = array_fill(0, self::BITS, 0);

        foreach ($tokens as $token => $weight) {
            // crc32 twice with different salts gives 64 bits without needing a
            // bignum: the hash only has to be stable and well distributed.
            $high = crc32('m1:'.$token);
            $low = crc32('m2:'.$token);

            for ($bit = 0; $bit < self::BITS; $bit++) {
                $source = $bit < 32 ? $low : $high;
                $set = ($source >> ($bit % 32)) & 1;
                $vector[$bit] += $set === 1 ? $weight : -$weight;
            }
        }

        // Built a nibble at a time. Going via base_convert() would put a
        // 64-bit value through a float on the way to decimal and quietly round
        // the low bits away — which made unrelated headlines collide at
        // distance zero, the one failure a deduplicator must never have.
        $hex = '';
        for ($nibble = 15; $nibble >= 0; $nibble--) {
            $value = 0;
            for ($bit = 3; $bit >= 0; $bit--) {
                $value = ($value << 1) | ($vector[$nibble * 4 + $bit] > 0 ? 1 : 0);
            }
            $hex .= dechex($value);
        }

        return $hex;
    }

    /**
     * Hamming distance: how many bits differ. Identical titles give 0.
     */
    public function distance(?string $a, ?string $b): int
    {
        if ($a === null || $b === null) {
            return self::BITS;
        }

        $x = str_pad($a, 16, '0', STR_PAD_LEFT);
        $y = str_pad($b, 16, '0', STR_PAD_LEFT);

        $distance = 0;

        // Nibble at a time, so this never depends on PHP's integer width.
        for ($i = 0; $i < 16; $i++) {
            $distance += substr_count(decbin(hexdec($x[$i]) ^ hexdec($y[$i])), '1');
        }

        return $distance;
    }

    /**
     * Token frequencies from a normalised title.
     *
     * @return array<string, int>
     */
    private function tokens(?string $text): array
    {
        $normalised = ($this->normaliser)($text);

        if (trim($normalised) === '') {
            return [];
        }

        $words = preg_split('/\s+/u', trim($normalised)) ?: [];

        $counts = [];
        foreach ($words as $word) {
            // One- and two-character tokens are mostly particles and carry no
            // signal about which story this is.
            if (mb_strlen($word) < 3) {
                continue;
            }

            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }

        return $counts;
    }
}
