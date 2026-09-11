<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HomepageLayout;
use App\Models\HomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageSection>
 */
class HomepageSectionFactory extends Factory
{
    protected $model = HomepageSection::class;

    public function definition(): array
    {
        return [
            'layout_id' => HomepageLayout::factory(),
            'type' => $this->faker->randomElement([
                'hero', 'featured_grid', 'category_strip', 'opportunities', 'latest',
            ]),
            'title' => ['ar' => 'أحدث المواد', 'en' => 'Latest'],
            'source' => 'auto',
            'config' => ['limit' => 6],
            'sort_order' => 0,
            'is_visible' => true,
        ];
    }

    public function manual(): static
    {
        return $this->state(['source' => 'manual', 'config' => ['article_ids' => []]]);
    }
}
