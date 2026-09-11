<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CompanyType;
use App\Models\Company;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->words(2, true)),
            'type' => CompanyType::Company,
            'industry_id' => null,
            'country_id' => null,
            'founded_year' => $this->faker->numberBetween(1975, 2024),
            'website' => $this->faker->url(),
            'ticker' => null,
            'logo_path' => null,
            'is_verified' => false,
            'mentions_count' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Company $company): void {
            if ($company->translations()->where('locale', 'ar')->doesntExist()) {
                $company->translations()->create([
                    'locale' => 'ar',
                    'name' => fake()->randomElement(ArabicContent::COMPANY_NAMES),
                    'short_description' => 'شركة عاملة في السوق المحلية ضمن قطاعها التشغيلي.',
                    'description' => fake()->randomElement(ArabicContent::PARAGRAPHS),
                ]);
            }
        });
    }

    public function startup(): static
    {
        return $this->state([
            'type' => CompanyType::Startup,
            'founded_year' => $this->faker->numberBetween(2018, 2025),
        ]);
    }

    public function listed(): static
    {
        return $this->state([
            'ticker' => (string) $this->faker->unique()->numberBetween(1010, 9999),
            'is_verified' => true,
        ]);
    }

    public function government(): static
    {
        return $this->state(['type' => CompanyType::Government, 'is_verified' => true]);
    }
}
