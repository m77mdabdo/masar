<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NotFound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotFound>
 */
class NotFoundFactory extends Factory
{
    protected $model = NotFound::class;

    public function definition(): array
    {
        return [
            'path' => '/ar/'.$this->faker->unique()->slug(3),
            'referrer' => null,
            'hits' => $this->faker->numberBetween(1, 40),
            'first_seen_at' => now()->subDays($this->faker->numberBetween(2, 60)),
            'last_seen_at' => now()->subHours($this->faker->numberBetween(1, 48)),
            'resolved' => false,
        ];
    }

    public function resolved(): static
    {
        return $this->state(['resolved' => true]);
    }
}
