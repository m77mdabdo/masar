<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClassificationPath;
use App\Enums\DocumentType;
use App\Enums\ReviewState;
use App\Models\IntelligenceItem;
use App\Models\Source;
use App\Models\SourceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntelligenceItem>
 */
class IntelligenceItemFactory extends Factory
{
    public function definition(): array
    {
        $sourceItem = SourceItem::factory();

        return [
            'source_item_id' => $sourceItem,
            'source_id' => fn (array $attributes) => SourceItem::find($attributes['source_item_id'])?->source_id
                ?? Source::factory(),
            'title' => 'هيئة تنظيمية تعتمد تعديلات على اللوائح',
            'summary' => null,
            'url' => 'https://example.test/news/'.$this->faker->unique()->slug(),
            'published_at' => now()->subHour(),
            'detected_at' => now(),
            'locale' => 'ar',
            'document_type' => DocumentType::Regulation,
            'classification_path' => ClassificationPath::Rules,
            'classification_evidence' => [],
            'importance' => 50,
            'importance_inputs' => [],
            'review_state' => ReviewState::New,
        ];
    }

    public function dismissed(int $daysAgo = 40): static
    {
        return $this->state([
            'review_state' => ReviewState::Rejected,
            'reviewed_at' => now()->subDays($daysAgo),
        ]);
    }
}
