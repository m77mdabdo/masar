<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdCampaign> */
class AdCampaignFactory extends Factory
{
    protected $model = AdCampaign::class;

    public function definition(): array
    {
        return [
            'name' => 'حملة تجريبية',
            'advertiser' => 'جهة معلنة',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(['starts_at' => now()->subMonths(2), 'ends_at' => now()->subDay()]);
    }

    public function future(): static
    {
        return $this->state(['starts_at' => now()->addWeek(), 'ends_at' => now()->addMonth()]);
    }

    public function paused(): static
    {
        return $this->state(['is_active' => false]);
    }
}
