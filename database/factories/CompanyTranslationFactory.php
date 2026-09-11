<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyTranslation;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyTranslation>
 */
class CompanyTranslationFactory extends Factory
{
    protected $model = CompanyTranslation::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'locale' => 'ar',
            'name' => $this->faker->randomElement(ArabicContent::COMPANY_NAMES),
            'short_description' => 'شركة عاملة في السوق المحلية ضمن قطاعها التشغيلي.',
            'description' => $this->faker->randomElement(ArabicContent::PARAGRAPHS),
        ];
    }
}
