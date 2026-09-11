<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Topic;
use App\Models\TopicTranslation;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TopicTranslation>
 */
class TopicTranslationFactory extends Factory
{
    protected $model = TopicTranslation::class;

    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'locale' => 'ar',
            'name' => $this->faker->randomElement(ArabicContent::TOPIC_NAMES),
            'description' => null,
        ];
    }
}
