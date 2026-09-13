<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Intelligence\StoreSourceItem;
use App\Enums\SourceLegalMode;
use App\Enums\SourceType;
use App\Models\Source;
use App\Services\Intelligence\ParsedItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A demo corpus for the inbox, and the sample the rules-classification rate is
 * measured against.
 *
 * Written as headlines a Saudi business desk would actually see, including the
 * awkward ones: a wire rewrite of a story we already have, an item whose
 * wording matches no keyword set, and one with no date. A corpus of clean cases
 * would produce a classification rate that means nothing.
 */
class IntelligenceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('IntelligenceSeeder skipped: local environment only.');

            return;
        }

        $sources = $this->sources();
        $this->publishLocalFeed();
        $store = app(StoreSourceItem::class);
        $stored = 0;

        foreach ($this->corpus() as $index => [$sourceKey, $title, $summary, $hoursAgo]) {
            $item = $store($sources[$sourceKey], new ParsedItem(
                title: $title,
                url: 'https://'.$sourceKey.'.test/news/'.($index + 1),
                summary: $summary,
                publishedAt: $hoursAgo === null ? null : Carbon::now()->subHours($hoursAgo),
            ));

            if ($item !== null) {
                $stored++;
            }
        }

        $this->command?->info("Intelligence: {$sources->count()} sources, {$stored} items.");
    }

    /**
     * A feed served by the local dev server, so `masar:check-sources --once`
     * can demonstrate a successful poll end to end.
     *
     * The demo publishers are fictional `.test` domains that resolve nowhere,
     * which exercises the failure path honestly and shows nothing of the fetch,
     * parse, dedup and classify path. This one is a real HTTP round trip
     * against a file we control.
     */
    private function publishLocalFeed(): void
    {
        $fixture = base_path('tests/Fixtures/Intelligence/ministry.rss.xml');

        if (! is_file($fixture)) {
            return;
        }

        $directory = storage_path('app/public/intelligence-demo');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        copy($fixture, $directory.'/local-feed.xml');

        $base = rtrim((string) config('app.url'), '/');

        Source::query()->updateOrCreate(
            ['url' => $base.'/storage/intelligence-demo'],
            [
                'name' => 'تغذية محلية للعرض',
                'feed_url' => $base.'/storage/intelligence-demo/local-feed.xml',
                'type' => SourceType::Rss,
                'locale' => 'ar',
                'trust_level' => 4,
                'is_active' => true,
                'poll_frequency_minutes' => 30,
                'legal_mode' => SourceLegalMode::Metadata,
            ],
        );
    }

    /** @return Collection<string, Source> */
    private function sources(): Collection
    {
        $definitions = [
            'ministry' => ['وزارة الصناعة والثروة المعدنية', SourceType::Rss, 5, null],
            'authority' => ['هيئة السوق المالية', SourceType::Rss, 5, null],
            'exchange' => ['السوق المالية السعودية', SourceType::Api, 5, 'earnings'],
            'agency' => ['وكالة الأنباء', SourceType::Atom, 4, null],
            'paper' => ['صحيفة اقتصادية', SourceType::Sitemap, 3, null],
            'wire' => ['خدمة أنباء إقليمية', SourceType::Rss, 2, null],
        ];

        return collect($definitions)->mapWithKeys(function (array $row, string $key): array {
            [$name, $type, $trust, $category] = $row;

            return [$key => Source::query()->updateOrCreate(
                ['url' => 'https://'.$key.'.test'],
                [
                    'name' => $name,
                    'feed_url' => 'https://'.$key.'.test/feed.xml',
                    'type' => $type,
                    'category' => $category,
                    'locale' => 'ar',
                    'trust_level' => $trust,
                    'is_active' => true,
                    'poll_frequency_minutes' => 30,
                    // Nothing here claims a right we have not been given.
                    'legal_mode' => SourceLegalMode::Metadata,
                ],
            )];
        });
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: ?string, 3: ?int}>
     */
    private function corpus(): array
    {
        return [
            ['ministry', 'الوزارة تعتمد تعديلات على لوائح التراخيص الصناعية', 'تدخل حيّز التنفيذ مع بداية الربع المقبل.', 2],
            ['ministry', 'طرح مناقصة عامة لتطوير بنية تحتية لوجستية في المنطقة الشرقية', 'كراسة الشروط متاحة عبر البوابة الوطنية.', 5],
            ['ministry', 'إطلاق جولة تراخيص جديدة في نشاط التعدين الاستكشافي', null, 9],
            ['ministry', 'صدر قرار بتنظيم نشاط الخدمات اللوجستية المتقدمة', null, 26],
            ['authority', 'الهيئة تقر ضوابط جديدة لإدراج الشركات في السوق الموازية', 'تشمل متطلبات إفصاح إضافية.', 3],
            ['authority', 'تعديلات على لائحة حوكمة الشركات المساهمة', null, 14],
            ['authority', 'الهيئة تعلن نتائج المراجعة السنوية لمؤشرات الالتزام', null, 30],
            ['exchange', 'شركة مدرجة تعلن نتائجها المالية للربع الثالث', 'ارتفاع في صافي الربح مقارنة بالفترة المماثلة.', 1],
            ['exchange', 'إعلان أرباح شركة صناعية للربع الثاني', null, 20],
            ['exchange', 'قوائم مالية أولية لشركة في قطاع التجزئة', null, 40],
            ['agency', 'تعيين رئيس تنفيذي جديد لشركة اتصالات مدرجة', 'يسري القرار مطلع الشهر المقبل.', 4],
            ['agency', 'إطلاق برنامج تمويل للمنشآت الصغيرة والمتوسطة', 'بضمانات جزئية تخفض كلفة الاقتراض.', 7],
            ['agency', 'ارتفاع مؤشر الإنفاق الاستهلاكي بنسبة ملحوظة', null, 11],
            ['agency', 'تدشين منصة رقمية لخدمات التجارة الخارجية', null, 18],
            ['paper', 'تقرير: قطاع الخدمات اللوجستية يسجل أعلى معدل نمو خلال خمس سنوات', null, 6],
            ['paper', 'دراسة حول أثر التحول الرقمي على المنشآت المتوسطة', null, 22],
            ['paper', 'ما الذي يعنيه التحول في مزيج الإيرادات لأصحاب الأعمال', 'قراءة تحليلية.', 12],
            ['paper', 'الرياض تستضيف فعالية اقتصادية إقليمية', null, 33],
            // A wire rewrite of the ministry's first item. Stage four should
            // flag it, and an editor should still see it.
            ['wire', 'الوزارة تعتمد تعديلات على لوائح التراخيص الصناعية الجديدة', null, 1],
            // Wording no keyword set reaches, from a source with no category.
            ['wire', 'حديث عن مرحلة جديدة في مسار القطاع', null, 8],
            ['wire', 'ملامح المشهد الاقتصادي بعد التغيرات الأخيرة', null, 16],
            ['wire', 'أصداء واسعة لما جرى هذا الأسبوع', null, null],
        ];
    }
}
