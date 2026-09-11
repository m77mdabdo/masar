<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->country()),
            'code' => $this->faker->unique()->countryCode(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Country $country): void {
            if ($country->translations()->where('locale', 'ar')->doesntExist()) {
                $country->translations()->create([
                    'locale' => 'ar',
                    'name' => 'دولة '.strtoupper((string) $country->code),
                ]);
            }
        });
    }

    public function saudiArabia(): static
    {
        return $this->state(['slug' => 'saudi-arabia', 'code' => 'SA'])
            ->afterCreating(function (Country $country): void {
                $country->translations()->updateOrCreate(
                    ['locale' => 'ar'],
                    ['name' => 'السعودية'],
                );
            });
    }
}
