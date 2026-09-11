<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Industry;
use App\Models\IndustryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndustryTranslation>
 */
class IndustryTranslationFactory extends Factory
{
    protected $model = IndustryTranslation::class;

    public function definition(): array
    {
        return [
            'industry_id' => Industry::factory(),
            'locale' => 'ar',
            'name' => 'اسم عربي',
        ];
    }
}
