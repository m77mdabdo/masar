<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Market;
use App\Models\MarketTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketTranslation>
 */
class MarketTranslationFactory extends Factory
{
    protected $model = MarketTranslation::class;

    public function definition(): array
    {
        return [
            'market_id' => Market::factory(),
            'locale' => 'ar',
            'name' => 'اسم عربي',
        ];
    }
}
