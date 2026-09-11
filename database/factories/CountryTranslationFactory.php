<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\CountryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CountryTranslation>
 */
class CountryTranslationFactory extends Factory
{
    protected $model = CountryTranslation::class;

    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'locale' => 'ar',
            'name' => 'اسم عربي',
        ];
    }
}
