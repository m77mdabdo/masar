<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * The seam, with nothing behind it yet.
 *
 * No provider is selected on purpose: the decision needs a real cost per
 * thousand from a week of actual volume, and that figure does not exist. What
 * exists is the interface it will implement and the null implementation that
 * lets everything downstream be written and tested today.
 *
 * Every method may decline. A caller that cannot handle "no answer" would make
 * the provider load-bearing, which is exactly what this abstraction exists to
 * prevent.
 */
interface AiProvider
{
    public function summarize(string $text, string $locale = 'ar'): ?string;

    /**
     * @return array{document_type: string, confidence: float}|null
     */
    public function classify(string $title, ?string $summary = null): ?array;

    /**
     * @return array<int, array{type: string, name: string}>
     */
    public function extractEntities(string $text): array;

    public function suggestHeadline(string $text, string $locale = 'ar'): ?string;
}
