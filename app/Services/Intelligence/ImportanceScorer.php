<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Source;
use App\Support\ArabicNormaliser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Source trust × document type × entity match × recency, 0–100.
 *
 * Every input is returned alongside the score, not just the total. An editor
 * must be able to see why something ranked high and disagree with it — a number
 * they cannot interrogate is a number they will learn to ignore, and then the
 * ranking is decoration.
 */
final class ImportanceScorer
{
    public function __construct(private readonly ArabicNormaliser $normaliser) {}

    /**
     * @return array{score: int, inputs: array<string, mixed>}
     */
    public function score(string $title, ?string $summary, Source $source, DocumentType $type, ?Carbon $publishedAt): array
    {
        $weights = (array) config('masar.intelligence.importance.weights', []);

        $inputs = [
            'source_trust' => [
                // 1-5 mapped to 0-1. A ministry and an aggregator are not the
                // same claim about the same fact.
                'value' => round(max(0, min(5, (int) $source->trust_level)) / 5, 3),
                'because' => $source->name.' — مستوى الثقة '.$source->trust_level,
            ],
            'document_type' => [
                'value' => $type->weight(),
                'because' => $type->label(),
            ],
            'entity_match' => $this->entityMatch($title, $summary),
            'recency' => $this->recency($publishedAt),
        ];

        $score = 0.0;
        foreach ($inputs as $key => $input) {
            $score += ((float) $input['value']) * ((float) ($weights[$key] ?? 0));
        }

        return [
            'score' => (int) round($score * 100),
            'inputs' => $inputs,
        ];
    }

    /**
     * Does this mention a company we already cover?
     *
     * Bounded deliberately: the company list is small and cached, and matching
     * against the whole entity graph on every ingested item would put a scan in
     * the ingestion path for a quarter of one score.
     *
     * @return array{value: float, because: string, matched?: array<int, string>}
     */
    private function entityMatch(string $title, ?string $summary): array
    {
        $haystack = ($this->normaliser)($title.' '.(string) $summary);

        if (trim($haystack) === '') {
            return ['value' => 0.0, 'because' => 'لا نص للمطابقة'];
        }

        $matched = $this->companyNames()
            ->filter(fn (string $name): bool => $name !== '' && str_contains($haystack, $name))
            ->take(5)
            ->values();

        if ($matched->isEmpty()) {
            return ['value' => 0.0, 'because' => 'لا تطابق مع شركة مغطاة'];
        }

        // Two mentions is meaningfully better than one; five is not five times
        // better than one, so it saturates.
        $value = min(1.0, 0.6 + (0.2 * ($matched->count() - 1)));

        return [
            'value' => round($value, 3),
            'because' => 'ذُكرت '.$matched->count().' شركة مغطاة',
            'matched' => $matched->all(),
        ];
    }

    /** @return Collection<int, string> */
    private function companyNames(): Collection
    {
        return cache()->remember('intelligence:company-names', 3600, function (): Collection {
            return Company::query()
                ->with('translations')
                ->get()
                ->flatMap(fn (Company $company): array => $company->translations
                    ->pluck('name')
                    ->push($company->ticker)
                    ->all())
                ->filter()
                ->map(fn (string $name): string => ($this->normaliser)($name))
                ->filter(fn (string $name): bool => mb_strlen($name) >= 3)
                ->unique()
                ->values();
        });
    }

    /**
     * @return array{value: float, because: string}
     */
    private function recency(?Carbon $publishedAt): array
    {
        if ($publishedAt === null) {
            // Unknown, not old. A feed without dates should not have every item
            // ranked as stale.
            return ['value' => 0.5, 'because' => 'لا تاريخ نشر'];
        }

        $window = (int) config('masar.intelligence.importance.recency_hours', 48);
        $age = max(0, $publishedAt->diffInMinutes(now()));
        $value = max(0.0, 1 - ($age / max(1, $window * 60)));

        return [
            'value' => round($value, 3),
            'because' => $publishedAt->diffForHumans(),
        ];
    }
}
