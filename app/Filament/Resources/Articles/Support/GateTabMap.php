<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Support;

/**
 * Points each publish-gate failure at the tab that fixes it.
 *
 * Telling an editor "the summary is missing" and leaving them to find where the
 * summary lives is only half an answer.
 */
class GateTabMap
{
    /**
     * @var array<string, array{tab: string, label: string}>
     */
    private const MAP = [
        'summary' => ['tab' => 'story', 'label' => 'افتح تبويب القصة'],
        'hero_image' => ['tab' => 'story', 'label' => 'افتح تبويب القصة'],
        'hero_alt' => ['tab' => 'story', 'label' => 'افتح تبويب القصة'],
        'sponsor_name' => ['tab' => 'story', 'label' => 'افتح تبويب القصة'],
        'why_it_matters' => ['tab' => 'differentiation', 'label' => 'افتح تبويب التمايز'],
        'source' => ['tab' => 'sources', 'label' => 'افتح تبويب المصادر'],
    ];

    /**
     * `fact_check` has no tab: it is a workflow stage, not a field. The message
     * says to move the article through fact-checking, and there is nothing on
     * this page to jump to.
     */
    private const NO_TAB = ['fact_check'];

    /**
     * @param  array<int, array{rule: string, message: string}>  $failures
     * @return array<int, array{message: string, tab: string, tab_label: string}>
     */
    public static function decorate(array $failures): array
    {
        return array_map(static function (array $failure): array {
            $target = self::MAP[$failure['rule']] ?? null;

            return [
                'message' => $failure['message'],
                'tab' => $target['tab'] ?? '',
                'tab_label' => in_array($failure['rule'], self::NO_TAB, true)
                    ? ''
                    : ($target['label'] ?? ''),
            ];
        }, $failures);
    }
}
