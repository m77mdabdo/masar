<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Market;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Market>
 */
class MarketFactory extends Factory
{
    protected $model = Market::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->words(2, true)),
            'code' => $this->faker->unique()->lexify('???'),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Market $market): void {
            if ($market->translations()->where('locale', 'ar')->doesntExist()) {
                $market->translations()->create([
                    'locale' => 'ar',
                    'name' => fake()->randomElement(ArabicContent::MARKET_NAMES),
                ]);
            }
        });
    }
}
