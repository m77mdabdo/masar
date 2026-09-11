<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'locale' => 'ar',
            'status' => 'pending',
            'verified_at' => null,
            'preferences' => ['frequency' => 'weekly', 'categories' => []],
            'visitor_id' => (string) Str::uuid(),
            'source' => $this->faker->randomElement(['footer', 'article_inline', 'homepage', 'exit_intent']),
            'unsubscribed_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state([
            'status' => 'confirmed',
            'verified_at' => now()->subDays($this->faker->numberBetween(1, 120)),
        ]);
    }

    public function unsubscribed(): static
    {
        return $this->state([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now()->subDays($this->faker->numberBetween(1, 30)),
        ]);
    }
}
