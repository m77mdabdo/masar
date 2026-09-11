<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            // Transliterated Latin slug — never URL-encoded Arabic.
            'slug' => Str::slug($this->faker->unique()->words(2, true)),
            'sort_order' => $this->faker->numberBetween(1, 20),
            'color' => $this->faker->hexColor(),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Category $category): void {
            if ($category->translations()->where('locale', 'ar')->doesntExist()) {
                $category->translations()->create([
                    'locale' => 'ar',
                    'name' => 'قسم '.$category->slug,
                    'description' => 'وصف مختصر للقسم التحريري ونطاق تغطيته.',
                ]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
