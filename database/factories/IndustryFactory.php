<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Industry;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Industry>
 */
class IndustryFactory extends Factory
{
    protected $model = Industry::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->words(2, true)),
            'code' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Industry $industry): void {
            if ($industry->translations()->where('locale', 'ar')->doesntExist()) {
                $industry->translations()->create([
                    'locale' => 'ar',
                    'name' => fake()->randomElement(ArabicContent::INDUSTRY_NAMES),
                ]);
            }
        });
    }
}
