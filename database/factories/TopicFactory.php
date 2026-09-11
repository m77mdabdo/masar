<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Topic;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->words(2, true)),
            'is_featured' => false,
            'articles_count' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Topic $topic): void {
            if ($topic->translations()->where('locale', 'ar')->doesntExist()) {
                $topic->translations()->create([
                    'locale' => 'ar',
                    'name' => fake()->randomElement(ArabicContent::TOPIC_NAMES),
                ]);
            }
        });
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
