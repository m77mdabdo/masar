<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdPlacement> */
class AdPlacementFactory extends Factory
{
    protected $model = AdPlacement::class;

    public function definition(): array
    {
        return [
            'key' => 'placement_'.$this->faker->unique()->numberBetween(1, 99999),
            'name' => 'مساحة إعلانية',
            'description' => null,
            'is_active' => true,
        ];
    }
}
