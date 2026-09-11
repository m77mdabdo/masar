<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The six editorial pillars.
 *
 * The order is an editorial statement, not an implementation detail: 1 to 3 are
 * the launch pillars and everything in the product — navigation, homepage
 * defaults, the Filament sidebar — follows `sort_order`.
 */
class CategorySeeder extends Seeder
{
    /**
     * @var array<int, array{slug: string, name: string, description: string, color: string}>
     */
    private const PILLARS = [
        [
            'slug' => 'saudi',
            'name' => 'السعودية والأسواق',
            'description' => 'تغطية الاقتصاد السعودي وأسواقه المالية والقرارات المؤثرة فيه.',
            'color' => '#0E2A1C',
        ],
        [
            'slug' => 'business',
            'name' => 'الأعمال والشركات',
            'description' => 'أخبار الشركات وتحركاتها التشغيلية والمالية.',
            'color' => '#1A4531',
        ],
        [
            'slug' => 'insights',
            'name' => 'رؤى وفرص',
            'description' => 'تحليلات معمّقة وفرص قابلة للتنفيذ.',
            'color' => '#1E5E3F',
        ],
        [
            'slug' => 'startups',
            'name' => 'الشركات الناشئة وريادة الأعمال',
            'description' => 'منظومة ريادة الأعمال وجولات التمويل.',
            'color' => '#7FB69A',
        ],
        [
            'slug' => 'global',
            'name' => 'عالمي بعدسة سعودية',
            'description' => 'التطورات العالمية التي تؤثر مباشرة على السوق السعودية.',
            'color' => '#1A4531',
        ],
        [
            'slug' => 'stories',
            'name' => 'قصص نجاح',
            'description' => 'تجارب ومسارات لشركات وأفراد في السوق.',
            'color' => '#0E2A1C',
        ],
    ];

    public function run(): void
    {
        foreach (self::PILLARS as $index => $pillar) {
            $category = Category::updateOrCreate(
                ['slug' => $pillar['slug']],
                [
                    'sort_order' => $index + 1,
                    'color' => $pillar['color'],
                    'is_active' => true,
                ],
            );

            $category->translations()->updateOrCreate(
                ['locale' => 'ar'],
                [
                    'name' => $pillar['name'],
                    'description' => $pillar['description'],
                    'meta_title' => $pillar['name'].' | مسار',
                    'meta_description' => $pillar['description'],
                ],
            );
        }
    }
}
