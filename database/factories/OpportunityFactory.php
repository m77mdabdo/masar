<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OpportunityPotential;
use App\Models\Opportunity;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        return [
            'locale' => 'ar',
            'translation_group_id' => (string) Str::uuid(),
            'slug' => Str::slug($this->faker->unique()->words(4, true)),
            'title' => 'فرصة استثمارية في قطاع '.$this->faker->randomElement(ArabicContent::INDUSTRY_NAMES),
            'summary' => $this->faker->randomElement(ArabicContent::OPPORTUNITY),
            'opportunity_type' => $this->faker->randomElement([
                'tender', 'license', 'funding', 'partnership', 'market_entry',
            ]),
            'industry_id' => null,
            'country_id' => null,
            'potential' => $this->faker->randomElement(OpportunityPotential::cases()),
            'requirements' => [
                'سجل تجاري ساري المفعول',
                'خبرة سابقة في القطاع لا تقل عن ثلاث سنوات',
            ],
            'deadline' => $this->faker->dateTimeBetween('+2 weeks', '+6 months'),
            'official_source_url' => $this->faker->url(),
            'status' => 'open',
            'published_at' => now()->subDays($this->faker->numberBetween(1, 60)),
        ];
    }

    public function highPotential(): static
    {
        return $this->state(['potential' => OpportunityPotential::High]);
    }

    public function lowPotential(): static
    {
        return $this->state(['potential' => OpportunityPotential::Low]);
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed']);
    }

    public function draft(): static
    {
        return $this->state(['published_at' => null]);
    }
}
