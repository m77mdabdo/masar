<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Country;
use App\Models\HomepageLayout;
use App\Models\Industry;
use App\Models\Market;
use App\Models\Menu;
use App\Models\Topic;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * What the site is made of, as opposed to what it says: taxonomy, menus and
 * the homepage layout. None of it is demo content — real articles need the same
 * countries, industries and topics — so none of it is flagged, and it is what
 * `masar:purge-demo` returns the database to.
 *
 * Split out of DemoContentSeeder so that line is drawn in code: this seeder is
 * the pre-seed baseline the purge is proved against.
 *
 * Every slug here is chosen, not generated: these appear in URLs and are
 * editorial decisions (CLAUDE.md §2).
 */
class SiteStructureSeeder extends Seeder
{
    /** @var array<string, string> Arabic name => slug */
    public const INDUSTRY_SLUGS = [
        'التقنية المالية' => 'fintech',
        'الطاقة' => 'energy',
        'العقار' => 'real-estate',
        'التجزئة' => 'retail',
        'الخدمات اللوجستية' => 'logistics',
        'الصناعات التحويلية' => 'manufacturing',
        'الاتصالات' => 'telecoms',
        'الرعاية الصحية' => 'healthcare',
        'التعليم' => 'education',
        'السياحة' => 'tourism',
        'التعدين' => 'mining',
        'الخدمات المالية' => 'financial-services',
        'الأغذية والزراعة' => 'food-and-agriculture',
    ];

    /** @var array<int, string> in ArabicContent::MARKET_NAMES order */
    private const MARKET_SLUGS = ['main-market', 'parallel-market', 'sukuk-and-bonds', 'derivatives', 'reits', 'government-debt'];

    /** @var array<string, string> Arabic name => slug */
    public const TOPIC_SLUGS = [
        'رؤية 2030' => 'vision-2030',
        'الاستثمار الأجنبي' => 'foreign-investment',
        'التقنية المالية' => 'fintech',
        'الطاقة المتجددة' => 'renewable-energy',
        'سلاسل الإمداد' => 'supply-chains',
        'التحول الرقمي' => 'digital-transformation',
        'سوق العقار' => 'real-estate-market',
        'الشركات الناشئة' => 'startups',
        'الاندماج والاستحواذ' => 'mergers-and-acquisitions',
        'أسواق الدين' => 'debt-markets',
        'الذكاء الاصطناعي' => 'artificial-intelligence',
        'التعدين' => 'mining',
        'السياحة' => 'tourism',
        'الخدمات اللوجستية' => 'logistics',
        'التجزئة' => 'retail',
        'الصناعات التحويلية' => 'manufacturing',
        'رأس المال الجريء' => 'venture-capital',
        'الحوكمة' => 'governance',
        'أسواق المال' => 'capital-markets',
        'ريادة الأعمال' => 'entrepreneurship',
    ];

    public function run(): void
    {
        $categories = Category::query()->orderBy('sort_order')->get();

        if ($categories->isEmpty()) {
            $this->command?->warn('SiteStructureSeeder skipped: run CategorySeeder first.');

            return;
        }

        $this->seedCountries();
        $this->seedIndustries();
        $this->seedMarkets();
        $this->seedTopics();
        $this->seedMenus($categories);
        $this->seedHomepage();
    }

    private function seedCountries(): void
    {
        foreach (ArabicContent::COUNTRIES as $row) {
            $country = Country::firstOrCreate(['slug' => $row['slug']], ['code' => $row['code']]);
            $country->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $row['name']]);
        }
    }

    private function seedIndustries(): void
    {
        foreach (self::INDUSTRY_SLUGS as $name => $slug) {
            $industry = Industry::firstOrCreate(['slug' => $slug]);
            $industry->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $name]);
        }
    }

    private function seedMarkets(): void
    {
        foreach (ArabicContent::MARKET_NAMES as $i => $name) {
            $market = Market::firstOrCreate(
                ['slug' => self::MARKET_SLUGS[$i]],
                ['code' => ArabicContent::MARKET_CODES[$i] ?? null],
            );
            $market->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $name]);
        }
    }

    private function seedTopics(): void
    {
        $i = 0;

        foreach (self::TOPIC_SLUGS as $name => $slug) {
            // The first five are featured: an "issue" is a featured topic.
            $topic = Topic::firstOrCreate(['slug' => $slug], ['is_featured' => $i++ < 5]);
            $topic->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $name]);
        }
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function seedMenus(Collection $categories): void
    {
        $menus = [
            'header' => ['name' => 'القائمة الرئيسية', 'desktop' => true, 'mobile' => false],
            'footer_explore' => ['name' => 'تذييل — استكشف', 'desktop' => true, 'mobile' => true],
            'footer_company' => ['name' => 'تذييل — المنصة', 'desktop' => true, 'mobile' => true],
            'mobile' => ['name' => 'قائمة الجوال', 'desktop' => false, 'mobile' => true],
        ];

        foreach ($menus as $key => $config) {
            $menu = Menu::firstOrCreate(['key' => $key], ['name' => $config['name']]);

            // footer_company is the only menu that is not a category list.
            if ($key === 'footer_company') {
                $this->seedCompanyMenuItems($menu);

                continue;
            }

            foreach ($categories as $index => $category) {
                // linkable_* rather than a hand-typed url: the item follows the
                // category when its slug changes, instead of rotting into a 404.
                $menu->items()->updateOrCreate(
                    [
                        'linkable_type' => $category->getMorphClass(),
                        'linkable_id' => $category->getKey(),
                    ],
                    [
                        'label' => [
                            'ar' => $category->translate('name', 'ar') ?? $category->slug,
                            'en' => ucfirst(str_replace('-', ' ', $category->slug)),
                        ],
                        'url' => null,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                        'show_desktop' => $config['desktop'],
                        'show_mobile' => $config['mobile'],
                    ],
                );
            }
        }
    }

    /**
     * Static pages have no entity to point at yet, so these carry a url until
     * the pages exist as real records.
     */
    private function seedCompanyMenuItems(Menu $menu): void
    {
        $items = [
            ['ar' => 'عن مسار', 'en' => 'About', 'url' => '/ar/about'],
            ['ar' => 'فريق التحرير', 'en' => 'Editorial team', 'url' => '/ar/team'],
            ['ar' => 'سياسة التصحيح', 'en' => 'Corrections policy', 'url' => '/ar/corrections'],
            ['ar' => 'أعلن معنا', 'en' => 'Advertise', 'url' => '/ar/advertise'],
            ['ar' => 'اتصل بنا', 'en' => 'Contact', 'url' => '/ar/contact'],
        ];

        foreach ($items as $index => $item) {
            $menu->items()->updateOrCreate(
                ['url' => $item['url']],
                [
                    'label' => ['ar' => $item['ar'], 'en' => $item['en']],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'show_desktop' => true,
                    'show_mobile' => true,
                ],
            );
        }
    }

    /**
     * The homepage as designed: one active layout, its ordered sections.
     *
     * `source` decides who fills each one — manual (an editor pinned content),
     * auto (a query in `config`), or mixed (pinned first, query fills the rest).
     * Pinned sections start empty here; DemoContentSeeder pins its articles,
     * and the purge unpins them.
     */
    private function seedHomepage(): void
    {
        $layout = HomepageLayout::firstOrCreate(
            ['name' => 'التخطيط الافتراضي'],
            ['is_active' => true, 'starts_at' => null, 'ends_at' => null],
        );

        $sections = [
            // Three slides, and the carousel's controls only exist because there
            // are three. A layout pinned to one renders without them.
            ['type' => 'big_story', 'title' => 'القصة الكبرى', 'source' => 'manual', 'config' => ['limit' => 3, 'article_ids' => []]],
            ['type' => 'tiles', 'title' => 'استكشف مسار', 'source' => 'auto', 'config' => ['limit' => 6, 'only_featured' => true]],
            ['type' => 'leads', 'title' => 'الأبرز', 'source' => 'mixed', 'config' => ['limit' => 3, 'article_ids' => []]],
            ['type' => 'saudi', 'title' => 'السعودية', 'source' => 'auto', 'config' => ['limit' => 6, 'category_slug' => 'saudi']],
            ['type' => 'markets', 'title' => 'الأسواق', 'source' => 'auto', 'config' => ['limit' => 5]],
            ['type' => 'business', 'title' => 'الأعمال والشركات', 'source' => 'auto', 'config' => ['limit' => 3, 'category_slug' => 'business']],
            ['type' => 'opportunities', 'title' => 'فرص', 'source' => 'auto', 'config' => ['limit' => 6]],
            ['type' => 'intelligence', 'title' => 'مسار الاستخباري', 'source' => 'auto', 'config' => ['limit' => 4, 'content_type' => 'analysis']],
            ['type' => 'insights', 'title' => 'رؤى وتحليلات', 'source' => 'auto', 'config' => ['limit' => 4, 'category_slug' => 'insights']],
            ['type' => 'stories', 'title' => 'قصص نجاح', 'source' => 'auto', 'config' => ['limit' => 5, 'category_slug' => 'stories']],
            ['type' => 'most_read', 'title' => 'الأكثر قراءة', 'source' => 'auto', 'config' => ['limit' => 10]],
            ['type' => 'editors_picks', 'title' => 'اختيارات المحرر', 'source' => 'auto', 'config' => ['limit' => 5]],
            ['type' => 'video', 'title' => 'مسار مرئي', 'source' => 'mixed', 'config' => ['limit' => 6, 'article_ids' => []]],
            ['type' => 'reports', 'title' => 'تقارير', 'source' => 'auto', 'config' => ['limit' => 5, 'content_type' => 'report']],
            ['type' => 'data', 'title' => 'بيانات', 'source' => 'auto', 'config' => ['limit' => 5, 'content_type' => 'explainer']],
            ['type' => 'issue', 'title' => 'عدد مسار 001', 'source' => 'manual', 'config' => ['limit' => 6, 'article_ids' => []]],
            ['type' => 'companies', 'title' => 'شركات في الأخبار', 'source' => 'auto', 'config' => ['limit' => 8]],
            ['type' => 'newsletter', 'title' => 'النشرة البريدية', 'source' => 'manual', 'config' => ['variant' => 'inline']],
        ];

        foreach ($sections as $index => $section) {
            $layout->sections()->updateOrCreate(
                ['type' => $section['type']],
                [
                    'title' => ['ar' => $section['title']],
                    'source' => $section['source'],
                    'config' => $section['config'],
                    'sort_order' => $index + 1,
                    'is_visible' => true,
                ],
            );
        }
    }
}
