<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Declines everything. The implementation that ships (owner's ruling: no
 * outside service). A draft it declines is left holding its source text with
 * the status AwaitingTranslator, for a person to translate in the editor.
 */
final class NullTranslationProvider implements TranslationProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function translate(array $segments, string $from, string $to, array $glossary): ?TranslationResult
    {
        return null;
    }
}
