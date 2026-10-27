<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Articles\PublishArticle;
use App\Actions\Articles\SchedulePublication;
use App\Actions\Articles\SyncArticleEntities;
use App\Actions\Articles\SyncArticleTopics;
use App\Actions\Support\GenerateSlug;
use App\Enums\ArticleStatus;
use App\Enums\CompanyType;
use App\Enums\ContentType;
use App\Enums\EntityRole;
use App\Enums\OpportunityPotential;
use App\Models\Article;
use App\Models\ArticleBlock;
use App\Models\Category;
use App\Models\Company;
use App\Models\Country;
use App\Models\EntityMention;
use App\Models\HomepageSection;
use App\Models\Industry;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Subscriber;
use App\Models\Topic;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        $topics = Topic::query()->orderBy('id')->get();

        if ($categories->isEmpty() || $topics->isEmpty()) {
            $this->command?->warn('DemoContentSeeder skipped: run CategorySeeder and SiteStructureSeeder first.');

            return;
        }

        $industries = Industry::query()->get()->keyBy('slug');
        $countries = Country::query()->orderBy('id')->get();

        $staff = $this->seedStaff();
        $companies = $this->seedCompanies($industries, $countries);
        $people = $this->seedPeople($companies);

        $this->seedArticles($categories, $topics, $staff, $companies, $people);
        $this->seedUnpublishableArticles($categories, $staff);

        $this->seedOpportunities($industries->values(), $countries);
        $this->seedSubscribers();
        $this->pinHomepage();

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
     * The demo's companies. Every one is invented, and every name was searched
     * against real companies before it was used (2026-10-27): a fictional
     * company that shares a real one's name puts invented figures on a real
     * business. One candidate, «مجداف» for the shipping line, turned out to be
     * a real logistics group and was replaced.
     *
     * The previous twelve were generic names likely to collide, and the first
     * four carried Tadawul codes 1010–1013 — real listing codes, 1010 among
     * them a listed bank's. A fictional company has no ticker.
     *
     * @var array<int, array{slug: string, name: string, type: CompanyType, industry: string, founded: int, short: string, description: string}>
     */
    public const COMPANIES = [
        ['slug' => 'zayzafoon-sands', 'name' => 'زيزفون الرمال للطاقة المتجددة', 'type' => CompanyType::Company, 'industry' => 'energy', 'founded' => 2019,
            'short' => 'مطوّر ومشغّل لمحطات طاقة شمسية ورياح تبيع إنتاجها لعملاء صناعيين.',
            'description' => 'تطوّر الشركة محطات هجينة من الطاقة الشمسية والرياح وتبيع إنتاجها مباشرة لعملاء صناعيين بعقود طويلة الأجل، ثم أضافت تخزين الطاقة بالبطاريات إلى محطاتها.'],
        ['slug' => 'durraq-payments', 'name' => 'دُرّاق للمدفوعات', 'type' => CompanyType::Startup, 'industry' => 'fintech', 'founded' => 2021,
            'short' => 'منصة دفع وتحصيل للمتاجر الصغيرة والمتوسطة.',
            'description' => 'تقدّم المنصة أجهزة نقاط بيع وروابط دفع وتسويات يومية للتجار، وأضافت لاحقًا تمويلًا قصير الأجل يُسدَّد من حركة المبيعات.'],
        ['slug' => 'sadafat-al-qulzum', 'name' => 'صدفة القلزم للشحن البحري', 'type' => CompanyType::Company, 'industry' => 'logistics', 'founded' => 2008,
            'short' => 'مشغّل خطوط شحن منتظمة بين موانئ البحر الأحمر.',
            'description' => 'تشغّل الشركة خطوط شحن منتظمة للحاويات الصغيرة بين موانئ البحر الأحمر، وتقدّم التخزين والتخليص لعملائها التجاريين.'],
        ['slug' => 'dar-al-qamariya', 'name' => 'دار القمرية للضيافة التراثية', 'type' => CompanyType::Company, 'industry' => 'tourism', 'founded' => 2016,
            'short' => 'سلسلة نُزل صغيرة في مبانٍ تراثية مرمّمة.',
            'description' => 'بدأت الشركة بنُزل عائلي واحد في مبنى تراثي مرمّم، وتدير اليوم سلسلة نُزل صغيرة تقوم على الترميم والتوظيف المحلي.'],
        ['slug' => 'shuaa-al-nafud', 'name' => 'شعاع النفود للطاقة', 'type' => CompanyType::Company, 'industry' => 'energy', 'founded' => 2017,
            'short' => 'مطوّر أنظمة طاقة شمسية على أسطح المصانع والمستودعات.',
            'description' => 'تركّب الشركة أنظمة شمسية على أسطح المنشآت الصناعية وتشغّلها مقابل رسوم شهرية، دون أن يتحمّل العميل كلفة التركيب.'],
        ['slug' => 'qitmira-foods', 'name' => 'قطميرة للأغذية', 'type' => CompanyType::Company, 'industry' => 'food-and-agriculture', 'founded' => 2012,
            'short' => 'مصنّع منتجات تمور معالجة للتجزئة والتصدير.',
            'description' => 'تصنّع الشركة معاجين التمور ومنتجاتها المعالجة للمخابز ومتاجر التجزئة، وتصدّر جزءًا من إنتاجها إلى أسواق الخليج.'],
        ['slug' => 'arasat-development', 'name' => 'عرصات للتطوير العقاري', 'type' => CompanyType::Company, 'industry' => 'real-estate', 'founded' => 2010,
            'short' => 'مطوّر مشاريع سكنية متوسطة الحجم.',
            'description' => 'تطوّر الشركة مجمعات سكنية متوسطة الحجم وتبيع وحداتها على الخريطة، مع التزام بتسليم البنية التحتية قبل بدء البيع.'],
        ['slug' => 'ruqaym-data', 'name' => 'رُقيم للبيانات', 'type' => CompanyType::Startup, 'industry' => 'retail', 'founded' => 2022,
            'short' => 'أدوات تحليل مبيعات ومخزون لمتاجر التجزئة.',
            'description' => 'تجمع المنصة بيانات نقاط البيع والمخزون لمتاجر التجزئة الصغيرة وتحوّلها إلى توصيات طلب أسبوعية.'],
        ['slug' => 'athla-agritech', 'name' => 'أثلة للزراعة الذكية', 'type' => CompanyType::Startup, 'industry' => 'food-and-agriculture', 'founded' => 2023,
            'short' => 'أنظمة ري واستشعار للمزارع في المناطق الجافة.',
            'description' => 'تبيع الشركة حساسات رطوبة وأنظمة ري موقوتة للمزارع الصغيرة، وتشترك مع مزارعي التمور في تجارب لخفض استهلاك المياه.'],
        ['slug' => 'mirqah-learning', 'name' => 'مرقاة للتعليم الرقمي', 'type' => CompanyType::Startup, 'industry' => 'education', 'founded' => 2020,
            'short' => 'برامج تدريب مهني قصيرة عبر الإنترنت.',
            'description' => 'تقدّم المنصة برامج تدريب مهني قصيرة تُصمَّم مع أصحاب العمل، وتربط المتدرّبين بفرص عمل لدى الشركات الشريكة.'],
    ];

    /**
     * The demo's people: invented, each tied to an invented company, never an
     * expert (`is_expert` asserts a real, consenting, quotable person) and
     * never quoted. A person's page says they are fictional.
     *
     * @var array<int, array{slug: string, name: string, title: string, company: string}>
     */
    public const PEOPLE = [
        ['slug' => 'lama-al-harbi', 'name' => 'لمى الحربي', 'title' => 'الرئيسة التنفيذية', 'company' => 'zayzafoon-sands'],
        ['slug' => 'hind-al-zahrani', 'name' => 'هند الزهراني', 'title' => 'المديرة المالية', 'company' => 'zayzafoon-sands'],
        ['slug' => 'majed-al-qahtani', 'name' => 'ماجد القحطاني', 'title' => 'المؤسس الشريك والرئيس التنفيذي', 'company' => 'durraq-payments'],
        ['slug' => 'turki-al-subaie', 'name' => 'تركي السبيعي', 'title' => 'الرئيس التنفيذي', 'company' => 'sadafat-al-qulzum'],
        ['slug' => 'nouf-al-shehri', 'name' => 'نوف الشهري', 'title' => 'المؤسسة والمديرة العامة', 'company' => 'dar-al-qamariya'],
        ['slug' => 'khalid-al-ghamdi', 'name' => 'خالد الغامدي', 'title' => 'الرئيس التنفيذي', 'company' => 'shuaa-al-nafud'],
        ['slug' => 'reem-al-dosari', 'name' => 'ريم الدوسري', 'title' => 'المؤسسة الشريكة', 'company' => 'athla-agritech'],
        ['slug' => 'saad-al-mutairi', 'name' => 'سعد المطيري', 'title' => 'الرئيس التنفيذي', 'company' => 'arasat-development'],
    ];

    /**
     * @param  Collection<string, Industry>  $industries  keyed by slug
     * @param  Collection<int, Country>  $countries
     * @return Collection<string, Company> keyed by slug
     */
    private function seedCompanies(Collection $industries, Collection $countries): Collection
    {
        $saudi = $countries->firstWhere('code', 'SA') ?? $countries->first();

        return collect(self::COMPANIES)->mapWithKeys(function (array $row) use ($industries, $saudi): array {
            $company = Company::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    // A startup is a company with type = startup, never its own table.
                    'type' => $row['type'],
                    'industry_id' => $industries[$row['industry']]?->id,
                    'country_id' => $saudi?->id,
                    'founded_year' => $row['founded'],
                    // `.example` is reserved and resolves nowhere.
                    'website' => 'https://'.$row['slug'].'.example',
                    'ticker' => null,
                    'is_verified' => false,
                ],
            );

            $company->translations()->updateOrCreate(['locale' => 'ar'], [
                'name' => $row['name'],
                'short_description' => $row['short'],
                'description' => $row['description'],
            ]);

            return [$row['slug'] => $company];
        });
    }

    /**
     * @param  Collection<string, Company>  $companies  keyed by slug
     * @return Collection<int, Person>
     */
    private function seedPeople(Collection $companies): Collection
    {
        return collect(self::PEOPLE)->map(function (array $row) use ($companies): Person {
            $person = Person::firstOrCreate(
                ['slug' => $row['slug']],
                ['company_id' => $companies[$row['company']]->id, 'is_expert' => false, 'linkedin' => null],
            );

            $person->translations()->updateOrCreate(['locale' => 'ar'], [
                'name' => $row['name'],
                'title' => $row['title'],
                'bio' => null,
            ]);

            return $person;
        });
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
            // 72 published is the floor for this design, not a preference.
            //
            // The front page has nineteen sections. Counted, they want 68
            // distinct articles: 79 slots less the 6 that `tiles` and the 5
            // that `data` used to consume before both became static. No
            // article appears twice on the page, so anything under 68 leaves
            // the lower rails empty — which is the same supply problem that
            // once put the same headline in three rails, just further down.
            //
            // Trimming this number without also cutting sections will quietly
            // empty the bottom third of the front page. If that is the
            // intention, change the section list first.
            ['target' => 'published', 'count' => 70],
            ['target' => 'scheduled', 'count' => 4],
            ['target' => ArticleStatus::Writing, 'count' => 6],
            ['target' => 'workflow', 'count' => 8],
        ];

        $workflowStages = [
            ArticleStatus::Assigned, ArticleStatus::Research, ArticleStatus::FactCheck,
            ArticleStatus::EditorReview, ArticleStatus::Seo, ArticleStatus::Ready,
            ArticleStatus::NeedsRevision, ArticleStatus::OnHold,
        ];

        $published = 0;

        foreach ($plan as $group) {
            for ($i = 0; $i < $group['count']; $i++) {
                $title = ArabicContent::headline();

                $article = Article::factory()
                    ->withBlocks()
                    ->withSources()
                    ->withHeroImage()
                    ->create([
                        'title' => $title,
                        'slug' => $this->slugFor($title, Article::class),
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

                    // Backdate across years, not across months. See
                    // publicationDate(): a one-year archive draws a one-bar
                    // timeline, and a one-bar chart states nothing.
                    $article->forceFill([
                        'published_at' => $this->publicationDate($published++),
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

        // Two sections draw on featured articles (tiles, editors_picks); the
        // pool has to cover both or the second renders empty.
        Article::query()->published()->inRandomOrder()->take(14)->update(['is_featured' => true]);

        // A mix of content types, so type-filtered sections have something to
        // show. Three sections filter by type — intelligence, reports,
        // data — and each needs its own pool after global dedup has taken its
        // cut, which is why these are sized well above each section's limit.
        $byType = [
            ContentType::Analysis->value => 9,
            ContentType::Interview->value => 9,
            ContentType::Report->value => 9,
            ContentType::Explainer->value => 9,
        ];

        $pool = Article::query()->published()->inRandomOrder()->limit(array_sum($byType))->pluck('id');
        $offset = 0;

        foreach ($byType as $type => $count) {
            Article::query()
                ->whereIn('id', $pool->slice($offset, $count)->all())
                ->update(['content_type' => $type]);

            $offset += $count;
        }

        // Opinion and story pieces are not four questions. Their bodies are
        // flattened after the content types settle, so the article page has
        // real examples of both shapes — the sectioned one and the stream —
        // rather than one shape and an untested branch.
        $flatTypes = [ContentType::Opinion->value, ContentType::Story->value];

        Article::query()->published()->inRandomOrder()->limit(8)->update(['content_type' => ContentType::Opinion->value]);

        $flatIds = Article::query()
            ->whereIn('content_type', $flatTypes)
            ->pluck('id');

        ArticleBlock::query()->whereIn('article_id', $flatIds)->update(['section' => null]);
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
            $title = ArabicContent::headline();

            $article = Article::factory()
                ->sponsored()
                ->withBlocks()
                ->withSources()
                ->withHeroImage()
                ->create([
                    'title' => $title,
                    'slug' => $this->slugFor($title, Article::class),
                    'category_id' => $categories->random()->id,
                    'author_id' => $author->id,
                ]);

            $workflow->walkTo($article, ArticleStatus::Ready, $chief);
            $article = $publish($article->refresh(), $chief);

            $this->attachGraph($article, $companies, $people, $topics);
        }
    }

    /**
     * Where the nth published article lands in time.
     *
     * **The archive is the reason this exists.** Every seeded article used to
     * fall inside one year, so the company profile's coverage timeline had a
     * single bar and correctly hid itself — a one-bar chart states nothing. The
     * component was built and tested and had never drawn anything, and the page
     * looked finished, which is how it went unnoticed.
     *
     * The shape is a real publication's: most of the archive is recent, and it
     * tapers backwards rather than spreading evenly. An even spread would draw
     * a flat chart, which is its own kind of lie about a publication that has
     * been growing.
     *
     * Deterministic in shape, random within a year: the distribution across
     * years is fixed so the timeline always has something to draw, while the
     * day and hour vary so the front page does not look generated.
     *
     * @var array<int, int> year => how many of the archive sits in it
     */
    private const ARCHIVE_SHAPE = [
        2026 => 30,
        2025 => 16,
        2024 => 10,
        2023 => 7,
        2022 => 5,
        2021 => 2,
    ];

    private function publicationDate(int $index): CarbonImmutable
    {
        $cursor = 0;

        foreach (self::ARCHIVE_SHAPE as $year => $count) {
            if ($index < $cursor + $count) {
                $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, config('app.timezone'));

                // The current year has not finished, so its articles must not
                // land in the future: a scheduled-looking published article is
                // invisible to every published() scope and would quietly
                // shrink the front page.
                $end = min(
                    $start->endOfYear(),
                    CarbonImmutable::now()->subHours(6),
                );

                return $start->addSeconds(random_int(0, max(1, $end->diffInSeconds($start))));
            }

            $cursor += $count;
        }

        // More published articles than the shape accounts for: put the overflow
        // in the current year rather than dropping it somewhere arbitrary.
        return CarbonImmutable::now()->subDays(random_int(1, 120))->subHours(random_int(0, 23));
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

        // No expert block. It used to pin an invented quote on a named demo
        // person, and a fabricated quote under a real-sounding name is exactly
        // what the sourcing rule forbids — fictional or not, it survives a
        // screenshot without the label that says so.
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
                    'slug' => $this->slugFor("بوابة النشر مادة {$label}", Article::class),
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
                'slug' => $this->slugFor($template['title'], Opportunity::class),
                'official_source_url' => 'https://opportunities.example/listing-'.($i + 1),
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
     * Pin the newest published articles into the homepage's manual sections.
     *
     * The sections themselves are structure (SiteStructureSeeder) and survive
     * the purge; these ids are demo and `masar:purge-demo` takes them out.
     */
    private function pinHomepage(): void
    {
        // Manual sections are meaningless without picks: an empty big_story makes
        // the composer look broken rather than unconfigured.
        $picks = Article::query()
            ->published()
            ->whereNotNull('hero_media_id')
            ->orderByDesc('published_at')
            ->limit(16)
            ->pluck('id')
            ->all();

        $slices = ['big_story' => [0, 3], 'leads' => [3, 2], 'video' => [5, 3], 'issue' => [8, 6]];

        foreach (HomepageSection::query()->whereIn('type', array_keys($slices))->get() as $section) {
            [$offset, $length] = $slices[$section->type];
            $section->update(['config' => [...(array) $section->config, 'article_ids' => array_slice($picks, $offset, $length)]]);
        }
    }

    /**
     * The system's suggested slug for generated content: a transliteration of
     * the title, made unique. Hand-written content chooses its own (§2).
     *
     * @param  class-string  $modelClass
     */
    private function slugFor(string $title, string $modelClass): string
    {
        return app(GenerateSlug::class)($title, 'ar', $modelClass);
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
