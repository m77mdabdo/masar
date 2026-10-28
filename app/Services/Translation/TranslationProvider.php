<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * The seam for a translation engine, with nothing behind it by ruling: the
 * owner decided (2026-10-28) that no outside service is used. What ships is
 * this interface and NullTranslationProvider, which declines.
 *
 * A provider receives plain-text segments whose protected tokens (URLs,
 * figures, Latin names) are already replaced by ⟦n⟧ markers, and returns
 * the same keys. It may decline by returning null; every caller treats that
 * as "a person translates this", never as an error.
 */
interface TranslationProvider
{
    public function name(): string;

    /**
     * @param  array<string, string>  $segments  path => protected plain text
     * @param  array<string, array<string, string>>  $glossary  term => [locale => rendering]
     */
    public function translate(array $segments, string $from, string $to, array $glossary): ?TranslationResult;
}
