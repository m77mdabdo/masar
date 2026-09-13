<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Sample market figures, written as settings rows.
 *
 * They arrive as data, through the same keys the settings screen writes, so an
 * editor opening الأسواق sees exactly these values and can change every one of
 * them. Nothing here is hardcoded in a Blade file, and nothing is fetched from
 * a feed.
 *
 * The source line says these are sample figures, in Arabic, on the page. That
 * matters: a demo that prints an unlabelled index value is a false market
 * statistic in front of anyone who opens it, and "it's only the demo" is not
 * a defence a reader ever hears.
 */
class MarketFiguresSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('MarketFiguresSeeder skipped: local environment only.');

            return;
        }

        $values = [
            'market.as_of' => now()->subHours(3)->format('Y-m-d H:i'),
            'market.source' => 'أرقام تجريبية — بيانات عرض، ليست قراءة سوق',

            'market.ticker' => [
                ['label' => 'تاسي', 'value' => '11,234.56', 'change' => 1.24],
                ['label' => 'نمو', 'value' => '26,310', 'change' => -0.41],
                ['label' => 'برنت', 'value' => '82.14', 'change' => -0.31],
                ['label' => 'الذهب', 'value' => '2,328.50', 'change' => 0.82],
                ['label' => 'دولار/ريال', 'value' => '3.75', 'change' => 0],
            ],

            'market.index' => [
                'label' => 'المؤشر العام للسوق',
                'value' => '11,234.56',
                'change' => 1.24,
                // A shape, not a series: the chart is drawn without a value axis.
                'series' => '38,50,32,74,60,96,82,118,104,136,148',
            ],

            'market.instruments' => [
                ['label' => 'برنت', 'value' => '82.14', 'change' => -0.31],
                ['label' => 'الذهب', 'value' => '2,328.50', 'change' => 0.82],
                ['label' => 'دولار/ريال', 'value' => '3.75', 'change' => null],
                ['label' => 'نمو', 'value' => '26,310', 'change' => -0.41],
            ],

            'market.pulse' => [
                ['label' => 'السعودية', 'value' => '+1.24%', 'change' => 1.24],
                ['label' => 'الإمارات', 'value' => '+0.63%', 'change' => 0.63],
                ['label' => 'مصر', 'value' => '−0.18%', 'change' => -0.18],
                ['label' => 'عالميًا', 'value' => '+0.41%', 'change' => 0.41],
            ],

            'market.data' => [
                ['label' => 'الناتج غير النفطي', 'value' => '+4.6%', 'change' => null],
                ['label' => 'الاستثمار الأجنبي', 'value' => '34 مليار دولار', 'change' => null],
                ['label' => 'تراخيص جديدة', 'value' => '3,210', 'change' => null],
                ['label' => 'البطالة', 'value' => '6.9%', 'change' => null],
            ],
        ];

        // Written through Settings::setMany, the same path the settings screen
        // uses — so the rows are shaped and audited exactly as an editor's would
        // be, rather than shaped the way a seeder guessed.
        app(Settings::class)->setMany($values);

        $this->command?->info('Market figures: '.count($values).' settings rows, labelled as sample data.');
    }
}
