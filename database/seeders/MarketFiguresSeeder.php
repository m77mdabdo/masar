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
            'market.source' => 'أرقام تجريبية — ليست قراءة سوق',

            // Each row carries its own shape points. The sparkline is drawn
            // from these and from nothing else, so a row an editor adds without
            // them gets a cell with no chart rather than an invented curve.
            // Direction agrees with `change` in every row on purpose: a rising
            // line above a negative figure is a contradiction a reader sees
            // before they read either one.
            'market.ticker' => [
                // Twelve points each, at the instrument's own level rather than
                // on an arbitrary 0-100 scale. That is what an editor would
                // actually type, and it is what the amplitude damping reads: a
                // series is scaled against its own level, so a normal trading
                // day fills the box and a pegged rate stays flat.
                ['label' => 'تاسي', 'value' => '11,234.56', 'change' => 1.24,
                    // low early, run up, pullback, close near the high
                    'series' => '11150,11120,11165,11210,11255,11238,11205,11248,11290,11272,11255,11235'],
                ['label' => 'نمو', 'value' => '26,310', 'change' => 0.63,
                    // shallower and choppier
                    'series' => '26150,26110,26180,26250,26215,26285,26250,26320,26290,26350,26320,26310'],
                ['label' => 'برنت', 'value' => '82.14', 'change' => -0.31,
                    // falling, through a failed recovery in the middle
                    'series' => '82.95,83.10,82.80,82.45,82.70,82.98,82.72,82.30,81.95,82.10,81.88,82.14'],
                ['label' => 'الذهب', 'value' => '2,328.50', 'change' => 0.82,
                    // rising steadily, small oscillations
                    'series' => '2312,2308,2318,2327,2322,2331,2327,2336,2331,2338,2332,2328'],
                // The riyal is pegged, and this is its real band. A dead
                // straight line reads as missing data; normalising this range
                // to the box would draw the steadiest instrument here as the
                // most volatile.
                ['label' => 'دولار/ريال', 'value' => '3.75', 'change' => 0,
                    'series' => '3.7500,3.7503,3.7498,3.7501,3.7500,3.7497,3.7502,3.7500,3.7503,3.7499,3.7501,3.7500'],
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
                ['label' => 'نمو', 'value' => '26,310', 'change' => 0.63],
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
