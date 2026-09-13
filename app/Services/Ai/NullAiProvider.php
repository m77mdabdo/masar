<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Declines everything, on purpose.
 *
 * This is the implementation that ships. It makes the AI path measurable
 * without buying anything: items that rules cannot classify fall through to
 * here, get no answer, and are recorded as unclassified — which is precisely
 * the number that tells us what an AI provider would be paid to do.
 */
final class NullAiProvider implements AiProvider
{
    public function summarize(string $text, string $locale = 'ar'): ?string
    {
        return null;
    }

    public function classify(string $title, ?string $summary = null): ?array
    {
        return null;
    }

    public function extractEntities(string $text): array
    {
        return [];
    }

    public function suggestHeadline(string $text, string $locale = 'ar'): ?string
    {
        return null;
    }
}
