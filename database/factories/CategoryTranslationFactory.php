<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryTranslation>
 */
class CategoryTranslationFactory extends Factory
{
    protected $model = CategoryTranslation::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'locale' => 'ar',
            'name' => 'قسم تحريري',
            'description' => 'وصف مختصر للقسم التحريري ونطاق تغطيته.',
            'meta_title' => null,
            'meta_description' => null,
        ];
    }
}
