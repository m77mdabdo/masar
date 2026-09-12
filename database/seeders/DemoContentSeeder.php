<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Articles\PublishArticle;
use App\Actions\Articles\SchedulePublication;
use App\Actions\Articles\SyncArticleEntities;
use App\Actions\Articles\SyncArticleTopics;
use App\Enums\ArticleStatus;
use App\Enums\CompanyType;
use App\Enums\EntityRole;
use App\Enums\OpportunityPotential;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Country;
use App\Models\EntityMention;
use App\Models\HomepageLayout;
use App\Models\Industry;
use App\Models\Market;
use App\Models\Menu;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Subscriber;
use App\Models\Topic;
use App\Models\User;
use Database\Factories\Support\ArabicContent;
use Database\Seeders\Support\ArticleWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * Local-only demo content. This is the dataset we judge the front-end against,
 * so it has to look like the real product: Arabic copy, a connected entity
 * graph, and articles spread across the whole workflow rather than all published.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DemoContentSeeder skipped: local environment only.');

            return;
        }

        $categories = Category::query()->orderBy('sort_order')->get();

        if ($categories->isEmpty()) {
            $this->command?->warn('DemoContentSeeder skipped: run CategorySeeder first.');

            return;
        }

        $staff = $this->seedStaff();
        $countries = $this->seedCountries();
        $industries = $this->seedIndustries();
        $this->seedMarkets();
        $topics = $this->seedTopics();
        $companies = $this->seedCompanies($industries, $countries);
        $people = $this->seedPeople($companies);

        $this->seedArticles($categories, $topics, $staff, $companies, $people);
        $this->seedUnpublishableArticles($categories, $staff);

        $this->seedOpportunities($industries, $countries);
        $this->seedSubscribers();
        $this->seedMenus($categories);
        $this->seedHomepage();

        $this->syncCounters();

        $this->command?->info(sprintf(
            'Demo content: %d articles, %d opportunities, %d companies, %d mentions.',
            Article::count(),
            Opportunity::count(),
            Company::count(),
            EntityMention::count(),
        ));
    }

    /**
     * @return Collection<int, User>
     */
    private function seedStaff(): Collection
    {
        $staff = collect();

        foreach ([
            ['رئيس التحرير', 'editor.in.chief@masar.test', 'editor_in_chief'],
            ['محرر أول', 'editor@masar.test', 'editor'],
            ['كاتب', 'writer@masar.test', 'writer'],
            ['مدقق معلومات', 'factchecker@masar.test', 'fact_checker'],
        ] as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password'],
            );

            $user->syncRoles([$role]);

            $staff->push($user);
        }

        return $staff;
    }

    /**
     * @return Collection<int, Country>
     */
    private function seedCountries(): Collection
    {
        return collect(ArabicContent::COUNTRIES)->map(function (array $row): Country {
            $country = Country::firstOrCreate(['slug' => $row['slug']], ['code' => $row['code']]);
            $country->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $row['name']]);

            return $country;
        });
    }

    /**
     * @return Collection<int, Industry>
     */
    private function seedIndustries(): Collection
    {
        return collect(ArabicContent::INDUSTRY_NAMES)->map(function (string $name, int $i): Industry {
            $industry = Industry::firstOrCreate(['slug' => 'industry-'.($i + 1)]);
            $industry->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $name]);

            return $industry;
        });
    }

    /**
     * @return Collection<int, Market>
     */
    private function seedMarkets(): Collection
    {
        return collect(ArabicContent::MARKET_NAMES)->map(function (string $name, int $i): Market {
            $market = Market::firstOrCreate(
                ['slug' => 'market-'.($i + 1)],
                ['code' => ArabicContent::MARKET_CODES[$i] ?? null],
            );
            $market->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $name]);

            return $market;
        });
    }

    /**
     * @return Collection<int, Topic>
     */
    private function seedTopics(): Collection
    {
        return collect(ArabicContent::TOPIC_NAMES)->map(function (string $name, int $i): Topic {
            $topic = Topic::firstOrCreate(
                ['slug' => 'topic-'.($i + 1)],
                ['is_featured' => $i < 5],
            );
            $topic->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $name]);

            return $topic;
        });
    }

    /**
     * @param  Collection<int, Industry>  $industries
     * @param  Collection<int, Country>  $countries
     * @return Collection<int, Company>
     */
    private function seedCompanies(Collection $industries, Collection $countries): Collection
    {
        $saudi = $countries->first();

        return collect(ArabicContent::COMPANY_NAMES)->map(function (string $name, int $i) use ($industries, $countries, $saudi): Company {
            $company = Company::firstOrCreate(
                ['slug' => 'company-'.($i + 1)],
                [
                    // A startup is a company with type = startup, never its own table.
                    'type' => $i >= 8 ? CompanyType::Startup : CompanyType::Company,
                    'industry_id' => $industries->random()->id,
                    // Saudi-primary: most coverage is domestic, a minority regional.
                    'country_id' => $i < 9 ? $saudi->id : $countries->random()->id,
                    'founded_year' => $i >= 8 ? rand(2019, 2024) : rand(1978, 2015),
                    'website' => 'https://example.test/company-'.($i + 1),
                    'ticker' => $i < 4 ? (string) (1010 + $i) : null,
                    'is_verified' => $i < 4,
                ],
            );

            $company->translations()->updateOrCreate(['locale' => 'ar'], [
                'name' => $name,
                'short_description' => 'شركة عاملة في السوق المحلية ضمن قطاعها التشغيلي.',
                'description' => ArabicContent::PARAGRAPHS[array_rand(ArabicContent::PARAGRAPHS)],
            ]);

            return $company;
        });
    }

    /**
     * @param  Collection<int, Company>  $companies
     * @return Collection<int, Person>
     */
    private function seedPeople(Collection $companies): Collection
    {
        // is_expert stays false: it asserts a real, consenting, quotable person,
        // and seeded demo people are none of those things.
        return Person::factory()
            ->count(8)
            ->state(fn (): array => ['company_id' => $companies->random()->id])
            ->create();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Topic>  $topics
     * @param  Collection<int, User>  $staff
     * @param  Collection<int, Company>  $companies
     * @param  Collection<int, Person>  $people
     */
    private function seedArticles(
        Collection $categories,
        Collection $topics,
        Collection $staff,
        Collection $companies,
        Collection $people,
    ): void {
        $author = $staff->firstWhere('email', 'writer@masar.test') ?? $staff->first();
        $editor = $staff->firstWhere('email', 'editor@masar.test') ?? $staff->first();
        $checker = $staff->firstWhere('email', 'factchecker@masar.test') ?? $staff->first();
        $chief = $staff->firstWhere('email', 'editor.in.chief@masar.test') ?? $staff->first();

        $workflow = app(ArticleWorkflow::class);
        $publish = app(PublishArticle::class);
        $schedule = app(SchedulePublication::class);

        // A realistic pipeline, not 40 published articles.
        $plan = [
            ['target' => 'published', 'count' => 22],
            ['target' => 'scheduled', 'count' => 4],
            ['target' => ArticleStatus::Writing, 'count' => 6],
            ['target' => 'workflow', 'count' => 8],
        ];

        $workflowStages = [
            ArticleStatus::Assigned, ArticleStatus::Research, ArticleStatus::FactCheck,
            ArticleStatus::EditorReview, ArticleStatus::Seo, ArticleStatus::Ready,
            ArticleStatus::NeedsRevision, ArticleStatus::OnHold,
        ];

        foreach ($plan as $group) {
            for ($i = 0; $i < $group['count']; $i++) {
                $article = Article::factory()
                    ->withBlocks()
                    ->withSources()
                    ->withHeroImage()
                    ->create([
                        'category_id' => $categories->random()->id,
                        'author_id' => $author->id,
                        'editor_id' => $editor->id,
                        'fact_checker_id' => $checker->id,
                    ]);

                $target = $group['target'] === 'workflow'
                    ? $workflowStages[array_rand($workflowStages)]
                    : $group['target'];

                // Status is only ever written through the Actions.
                if ($target === 'published') {
                    $workflow->walkTo($article, ArticleStatus::Ready, $chief);
                    $article = $publish($article->refresh(), $chief);

                    // Backdate so the demo has a spread of publication dates.
                    $article->forceFill([
                        'published_at' => now()->subDays(random_int(1, 180))->subHours(random_int(0, 23)),
                        'views_count' => random_int(50, 25_000),
                    ])->save();
                } elseif ($target === 'scheduled') {
                    $workflow->walkTo($article, ArticleStatus::Ready, $chief);
                    $article = $schedule(
                        $article->refresh(),
                        now()->addDays(random_int(1, 21)),
                        $chief,
                    );
                } else {
                    $article = $workflow->walkTo($article, $target, $chief);
                }

                $this->attachGraph($article, $companies, $people, $topics);
            }
        }

        $this->seedSponsoredArticles($categories, $topics, $companies, $people, $staff);

        Article::query()->published()->inRandomOrder()->take(4)->update(['is_featured' => true]);
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Topic>  $topics
     * @param  Collection<int, Company>  $companies
     * @param  Collection<int, Person>  $people
     * @param  Collection<int, User>  $staff
     */
    private function seedSponsoredArticles(
        Collection $categories,
        Collection $topics,
        Collection $companies,
        Collection $people,
        Collection $staff,
    ): void {
        $author = $staff->firstWhere('email', 'writer@masar.test') ?? $staff->first();
        $chief = $staff->firstWhere('email', 'editor.in.chief@masar.test') ?? $staff->first();

        $workflow = app(ArticleWorkflow::class);
        $publish = app(PublishArticle::class);

        foreach (range(1, 2) as $ignored) {
            $article = Article::factory()
                ->sponsored()
                ->withBlocks()
                ->withSources()
                ->withHeroImage()
                ->create([
                    'category_id' => $categories->random()->id,
                    'author_id' => $author->id,
                ]);

            $workflow->walkTo($article, ArticleStatus::Ready, $chief);
            $article = $publish($article->refresh(), $chief);

            $this->attachGraph($article, $companies, $people, $topics);
        }
    }

    /**
     * Wire the article into the content graph through the real Actions, so the
     * denormalised counters are maintained the same way they will be in
     * production rather than patched up afterwards.
     *
     * Mentions are attached after publication so entity_mentions.created_at
     * matches published_at, which is what EntityContentQuery orders by.
     *
     * @param  Collection<int, Company>  $companies
     * @param  Collection<int, Person>  $people
     * @param  Collection<int, Topic>  $topics
     */
    private function attachGraph(
        Article $article,
        Collection $companies,
        Collection $people,
        Collection $topics,
    ): void {
        $picked = $companies->random(random_int(1, 3));
        $mentions = [];

        foreach ($picked as $index => $company) {
            $mentions[] = [
                'type' => $company->getMorphClass(),
                'id' => $company->getKey(),
                'role' => $index === 0 ? EntityRole::Primary : EntityRole::Secondary,
                'prominence' => $index === 0 ? random_int(70, 100) : random_int(30, 69),
            ];
        }

        $person = $people->random();
        $mentions[] = [
            'type' => $person->getMorphClass(),
            'id' => $person->getKey(),
            'role' => EntityRole::Mentioned,
            'prominence' => random_int(10, 50),
        ];

        app(SyncArticleEntities::class)($article->refresh(), $mentions);
        app(SyncArticleTopics::class)($article, $topics->random(random_int(2, 4))->pluck('id')->all());
    }

    /**
     * Articles parked at `ready` that the publish gate must refuse — one per rule.
     *
     * These exist so the Filament publish button and the gate panel have real
     * content to render against. If any of these ever becomes publishable, the
     * gate has regressed.
     *
     * They are walked to `ready` through the Actions like everything else, and
     * only then given their defect: the defect is in editorial fields, never in
     * `status`.
     *
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, User>  $staff
     */
    private function seedUnpublishableArticles(Collection $categories, Collection $staff): void
    {
        $author = $staff->firstWhere('email', 'writer@masar.test') ?? $staff->first();
        $chief = $staff->firstWhere('email', 'editor.in.chief@masar.test') ?? $staff->first();

        $workflow = app(ArticleWorkflow::class);

        $defects = [
            'بلا ملخص 30 ثانية' => fn (Article $a) => $a->forceFill(['summary' => null]),
            'بلا مصدر موثّق' => function (Article $a): Article {
                $a->sources()->delete();
                $a->sources()->create([
                    'title' => 'إفادة شفهية غير موثقة برابط',
                    'url' => null,
                    'source_type' => 'interview',
                    'sort_order' => 0,
                ]);

                return $a;
            },
            'بلا صورة غلاف' => fn (Article $a) => $a->forceFill(['hero_media_id' => null]),
            'صورة غلاف بلا نص بديل' => fn (Article $a) => $a->forceFill(['hero_alt' => null]),
            'لم تمر بالتدقيق' => fn (Article $a) => $a->forceFill([
                'fact_checked_at' => null,
                'fact_checker_id' => null,
            ]),
            'بلا فقرة لماذا يهم هذا' => fn (Article $a) => $a->forceFill(['why_it_matters' => null]),
            'محتوى مدفوع بلا اسم راعٍ' => fn (Article $a) => $a->forceFill([
                'is_sponsored' => true,
                'sponsor_name' => null,
            ]),
        ];

        foreach ($defects as $label => $applyDefect) {
            // Every one of these starts complete — hero image included — so the
            // defect applied below is the single reason the gate refuses it.
            $article = Article::factory()
                ->withBlocks()
                ->withSources()
                ->withHeroImage()
                ->create([
                    'title' => "[بوابة النشر] مادة {$label}",
                    'category_id' => $categories->random()->id,
                    'author_id' => $author->id,
                ]);

            $article = $workflow->walkTo($article, ArticleStatus::Ready, $chief);

            $applyDefect($article->refresh())->save();
        }
    }

    /**
     * @param  Collection<int, Industry>  $industries
     * @param  Collection<int, Country>  $countries
     */
    private function seedOpportunities(Collection $industries, Collection $countries): void
    {
        $saudi = $countries->first();
        $potentials = OpportunityPotential::cases();

        foreach (range(0, 17) as $i) {
            $template = ArabicContent::OPPORTUNITIES[$i % count(ArabicContent::OPPORTUNITIES)];

            // Cycle the three potentials so every band has six entries.
            $potential = $potentials[$i % count($potentials)];

            Opportunity::factory()->create([
                'slug' => 'opportunity-'.($i + 1),
                'title' => $template['title'],
                'summary' => $template['summary'],
                'opportunity_type' => $template['type'],
                'industry_id' => $industries->random()->id,
                'country_id' => $i < 14 ? $saudi->id : $countries->random()->id,
                'potential' => $potential,
                // A few closed and a few unpublished, so filters have something to do.
                'status' => $i >= 15 ? 'closed' : 'open',
                'published_at' => $i === 17 ? null : now()->subDays(rand(1, 90)),
            ]);
        }
    }

    private function seedSubscribers(): void
    {
        // The mix matters: returning-audience reporting has to cope with
        // unconfirmed and churned rows, not just a clean confirmed list.
        Subscriber::factory()->count(38)->confirmed()->create();
        Subscriber::factory()->count(14)->create();
        Subscriber::factory()->count(8)->unsubscribed()->create();
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
     * The homepage as designed: one active layout, thirteen ordered sections.
     *
     * `source` decides who fills each one — manual (an editor pinned content),
     * auto (a query in `config`), or mixed (pinned first, query fills the rest).
     */
    private function seedHomepage(): void
    {
        $layout = HomepageLayout::firstOrCreate(
            ['name' => 'التخطيط الافتراضي'],
            ['is_active' => true, 'starts_at' => null, 'ends_at' => null],
        );

        $sections = [
            ['type' => 'big_story', 'title' => 'القصة الكبرى', 'source' => 'manual', 'config' => ['limit' => 1, 'article_ids' => []]],
            ['type' => 'leads', 'title' => 'الأبرز', 'source' => 'mixed', 'config' => ['limit' => 4, 'article_ids' => []]],
            ['type' => 'tiles', 'title' => 'مختارات', 'source' => 'auto', 'config' => ['limit' => 6, 'only_featured' => true]],
            ['type' => 'saudi', 'title' => 'السعودية والأسواق', 'source' => 'auto', 'config' => ['limit' => 5, 'category_slug' => 'saudi']],
            ['type' => 'markets', 'title' => 'الأسواق', 'source' => 'auto', 'config' => ['limit' => 4, 'category_slug' => 'saudi', 'topic_slug' => null]],
            ['type' => 'business', 'title' => 'الأعمال والشركات', 'source' => 'auto', 'config' => ['limit' => 5, 'category_slug' => 'business']],
            ['type' => 'opportunities', 'title' => 'فرص', 'source' => 'auto', 'config' => ['limit' => 4, 'potential' => 'high']],
            ['type' => 'intelligence', 'title' => 'رصد وتحليل', 'source' => 'auto', 'config' => ['limit' => 4, 'content_type' => 'analysis']],
            ['type' => 'insights', 'title' => 'رؤى وفرص', 'source' => 'auto', 'config' => ['limit' => 5, 'category_slug' => 'insights']],
            ['type' => 'stories', 'title' => 'قصص نجاح', 'source' => 'auto', 'config' => ['limit' => 4, 'category_slug' => 'stories']],
            ['type' => 'video', 'title' => 'مرئيات', 'source' => 'manual', 'config' => ['limit' => 3, 'article_ids' => []]],
            ['type' => 'issue', 'title' => 'ملف العدد', 'source' => 'manual', 'config' => ['limit' => 6, 'article_ids' => []]],
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

    /**
     * Counters are already maintained by SyncArticleEntities and
     * SyncArticleTopics as the graph is built. This runs the same rebuild that
     * `masar:recount` uses, purely to assert the write path got it right — if
     * these two ever disagree, the write path has a bug.
     */
    private function syncCounters(): void
    {
        Artisan::call('masar:recount');
    }
}
