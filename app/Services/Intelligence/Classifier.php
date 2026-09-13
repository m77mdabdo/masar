<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Enums\ClassificationPath;
use App\Enums\DocumentType;
use App\Models\Source;
use App\Services\Ai\AiProvider;
use App\Support\ArabicNormaliser;

/**
 * Rules first, AI second, and the path recorded either way.
 *
 * That last part is the point. When we argue about whether an AI provider earns
 * its cost per thousand, the argument is this column against a month of volume,
 * not two opinions. Ship the null provider and the number tells us what we
 * would be paying it to do.
 */
final class Classifier
{
    public function __construct(
        private readonly ArabicNormaliser $normaliser,
        private readonly AiProvider $ai,
    ) {}

    /**
     * @return array{type: DocumentType, path: ClassificationPath, evidence: array<string, mixed>}
     */
    public function classify(string $title, ?string $summary, Source $source): array
    {
        $rules = $this->byKeywords($title, $summary);

        if ($rules !== null) {
            return $rules;
        }

        // The source's own category is a weak rule, but it is a rule: a feed
        // that only ever publishes tenders tells us something about an entry
        // whose wording we did not recognise.
        $byCategory = $this->bySourceCategory($source);

        if ($byCategory !== null) {
            return $byCategory;
        }

        $ai = $this->ai->classify($title, $summary);

        if ($ai !== null && ($type = DocumentType::tryFrom((string) ($ai['document_type'] ?? ''))) !== null) {
            return [
                'type' => $type,
                'path' => ClassificationPath::Ai,
                'evidence' => ['confidence' => $ai['confidence'] ?? null],
            ];
        }

        // Nothing reached it. Recorded honestly rather than defaulted to
        // something plausible — this is the number that sizes the AI decision.
        return [
            'type' => DocumentType::Other,
            'path' => ClassificationPath::Unclassified,
            'evidence' => [],
        ];
    }

    /**
     * @return array{type: DocumentType, path: ClassificationPath, evidence: array<string, mixed>}|null
     */
    private function byKeywords(string $title, ?string $summary): ?array
    {
        // The title carries the form; the summary is corroboration. Weighted so
        // a headline that says "قرار" beats a summary that mentions "تقرير".
        $haystackTitle = ($this->normaliser)($title);
        $haystackBody = ($this->normaliser)((string) $summary);

        $scores = [];
        $matched = [];

        foreach ((array) config('masar.intelligence.classification', []) as $type => $keywords) {
            foreach ((array) $keywords as $keyword) {
                $needle = ($this->normaliser)((string) $keyword);

                if ($needle === '') {
                    continue;
                }

                if (str_contains($haystackTitle, $needle)) {
                    $scores[$type] = ($scores[$type] ?? 0) + 2;
                    $matched[$type][] = $keyword;
                } elseif ($haystackBody !== '' && str_contains($haystackBody, $needle)) {
                    $scores[$type] = ($scores[$type] ?? 0) + 1;
                    $matched[$type][] = $keyword;
                }
            }
        }

        if ($scores === []) {
            return null;
        }

        arsort($scores);
        $winner = (string) array_key_first($scores);
        $type = DocumentType::tryFrom($winner);

        if ($type === null) {
            return null;
        }

        return [
            'type' => $type,
            'path' => ClassificationPath::Rules,
            'evidence' => [
                'matched' => array_values(array_unique($matched[$winner] ?? [])),
                'score' => $scores[$winner],
                'runners_up' => array_slice($scores, 1, 2, true),
            ],
        ];
    }

    /**
     * @return array{type: DocumentType, path: ClassificationPath, evidence: array<string, mixed>}|null
     */
    private function bySourceCategory(Source $source): ?array
    {
        $type = DocumentType::tryFrom((string) $source->category);

        return $type === null ? null : [
            'type' => $type,
            'path' => ClassificationPath::Rules,
            'evidence' => ['from' => 'source_category', 'category' => $source->category],
        ];
    }
}
