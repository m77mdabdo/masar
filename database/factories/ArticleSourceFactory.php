<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleSource;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleSource>
 */
class ArticleSourceFactory extends Factory
{
    protected $model = ArticleSource::class;

    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'title' => 'بيان صادر عن الجهة المختصة',
            'url' => $this->faker->url(),
            'publisher' => $this->faker->randomElement(ArabicContent::SOURCE_PUBLISHERS),
            'source_type' => $this->faker->randomElement([
                'official_document', 'press_release', 'interview', 'report', 'data',
            ]),
            'accessed_at' => now()->subDays($this->faker->numberBetween(1, 60)),
            'sort_order' => 0,
        ];
    }

    /**
     * A source with no URL — does not satisfy the publish gate.
     */
    public function withoutUrl(): static
    {
        return $this->state(['url' => null]);
    }
}
