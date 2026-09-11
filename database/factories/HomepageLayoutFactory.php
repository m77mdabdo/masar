<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HomepageLayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageLayout>
 */
class HomepageLayoutFactory extends Factory
{
    protected $model = HomepageLayout::class;

    public function definition(): array
    {
        return [
            'name' => 'تخطيط الصفحة الرئيسية',
            'is_active' => false,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}
