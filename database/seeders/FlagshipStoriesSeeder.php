<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Articles\PublishArticle;
use App\Actions\Articles\SyncArticleEntities;
use App\Actions\Articles\SyncArticleTopics;
use App\Enums\ArticleSection;
use App\Enums\ArticleStatus;
use App\Enums\ContentType;
use App\Enums\EntityRole;
use App\Enums\OpportunityPotential;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Country;
use App\Models\EntityMention;
use App\Models\Industry;
use App\Models\Market;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Topic;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\ArticleWorkflow;
use Illuminate\Database\Seeder;

/**
 * The demo's flagship content: eleven articles written to the full editorial
 * model, and one opportunity, about the invented companies in
 * DemoContentSeeder::COMPANIES.
 *
 * The rules this content is held to (owner's ruling, 2026-10-27):
 *
 * - Every company and person is invented, and every figure is about an
 *   invented company. No real company, ministry, regulator, exchange or
 *   official is named with a number attached. Places are real; claims about
 *   them are not made.
 * - Every figure is attributed to the invented company's own statement, and
 *   every source says in its title that the entity is fictional. Source URLs
 *   are on `.example`, which is reserved and resolves nowhere.
 * - Nobody is quoted. A fabricated quote under a real-sounding name survives a
 *   screenshot without the label that says it is fictional.
 * - Each article passes the publish gate through PublishArticle, not around it.
 * - Slugs are chosen, not generated (CLAUDE.md §2).
 *
 * The figures agree with each other across articles and with the sample data
 * grid in MarketFiguresSeeder: Zayzafoon Sands reaches 420 MW (120 + 300),
 * Sadafat Al-Qulzum runs its fifth line in 2024 and sixth in 2026, Durraq
 * processes 1.4 billion riyals a month, Dar Al-Qamariya runs nine lodges.
 *
 * Zayzafoon Sands has coverage in 2022, 2023, 2024, 2025 and 2026 — an
 * analysis, a report and an opportunity among it — so its company profile has
 * a timeline with something to draw and aggregates three content shapes.
 */
class FlagshipStoriesSeeder extends Seeder
{
    /**
     * The photograph each flagship article is illustrated with, chosen for its
     * subject. ImageLibrarySeeder honours this before falling back to category
     * affinity. Eleven files, no repeats.
     *
     * @var array<string, string> slug => catalogue file
     */
    public const HEROES = [
        'zayzafoon-sands-first-direct-power-contract' => 'pexels-kindelmedia-9800092.jpg',
        'durraq-payments-first-funding-round' => 'pexels-hristo-sahatchiev-273072-821357.jpg',
        'zayzafoon-sands-first-plant-begins-operation' => 'pexels-alfomedeiros-15268778.jpg',
        'why-small-power-developers-sell-direct-to-factories' => 'pexels-igor-passchier-111147847-32399139.jpg',
        'sadafat-al-qulzum-fifth-red-sea-line' => 'pexels-introspectivedsgn-9407367.jpg',
        'masar-report-financing-the-second-power-plant' => 'pexels-pam-santos-2153999487-37274707.jpg',
        'dar-al-qamariya-from-one-lodge-to-nine' => 'pexels-abdullahg-11695874.jpg',
        'zayzafoon-sands-green-sukuk-private-placement' => 'pexels-alesiakozik-6781273.jpg',
        'sadafat-al-qulzum-opens-djibouti-office' => 'pexels-diego-f-parra-33199-24244231.jpg',
        'zayzafoon-sands-adds-battery-storage' => 'pexels-tima-miroshnichenko-5717315.jpg',
        'durraq-payments-launches-merchant-financing' => 'pexels-aburhman187-29859597.jpg',
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('FlagshipStoriesSeeder skipped: local environment only.');

            return;
        }

        $writer = User::query()->where('email', 'writer@masar.test')->first();
        $chief = User::query()->where('email', 'editor.in.chief@masar.test')->first();

        if ($writer === null || $chief === null || Company::query()->where('slug', 'zayzafoon-sands')->doesntExist()) {
            $this->command?->warn('FlagshipStoriesSeeder skipped: run DemoContentSeeder first.');

            return;
        }

        foreach ($this->stories() as $story) {
            $this->publish($story, $writer, $chief);
        }

        $this->seedOpportunity();

        $this->command?->info('Flagship: '.count($this->stories()).' articles and 1 opportunity.');
    }

    /**
     * @param  array<string, mixed>  $story
     */
    private function publish(array $story, User $writer, User $chief): void
    {
        $article = Article::factory()->withHeroImage()->create([
            'slug' => $story['slug'],
            'title' => $story['title'],
            'subtitle' => $story['subtitle'],
            'category_id' => Category::query()->where('slug', $story['category'])->value('id'),
            'content_type' => $story['type'],
            'author_id' => $writer->id,
            'summary' => $story['summary'],
            'body' => null,
            'what_happened' => $story['what'],
            'why_it_matters' => $story['why'],
            'business_impact' => $story['who'],
            'opportunity' => $story['opportunity'],
            'key_numbers' => $story['numbers'],
            'is_featured' => $story['featured'] ?? false,
        ]);

        $order = 0;

        foreach ($story['sections'] as $section => [$subhead, $text]) {
            // A heading leading a section becomes that section's subhead (§5).
            $article->blocks()->create(['section' => $section, 'type' => 'heading', 'data' => ['level' => 3, 'text' => $subhead], 'sort_order' => $order++]);
            $article->blocks()->create(['section' => $section, 'type' => 'paragraph', 'data' => ['text' => $text], 'sort_order' => $order++]);
        }

        foreach ($story['sources'] as $i => $source) {
            $article->sources()->create($source + [
                'source_type' => 'press_release',
                'accessed_at' => CarbonImmutable::parse($story['date'])->subDay(),
                'sort_order' => $i,
            ]);
        }

        // Mentions before publication: PublishArticle realigns their
        // timestamps to published_at, which the company timeline orders by.
        app(SyncArticleEntities::class)($article->refresh(), $this->mentions($story['graph']));
        app(SyncArticleTopics::class)($article, Topic::query()->whereIn('slug', $story['graph']['topics'])->pluck('id')->all());

        $article = app(ArticleWorkflow::class)->walkTo($article->refresh(), ArticleStatus::Ready, $chief);

        // The date is the story's, set before publishing so PublishArticle
        // keeps it rather than stamping today.
        $article->forceFill(['published_at' => CarbonImmutable::parse($story['date'], config('app.timezone'))])->save();

        app(PublishArticle::class)($article->refresh(), $chief);
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<int, array{type: string, id: int, role: EntityRole, prominence: int}>
     */
    private function mentions(array $graph): array
    {
        $rows = [];

        foreach ($graph['companies'] as [$slug, $role, $prominence]) {
            $rows[] = ['type' => 'company', 'id' => Company::query()->where('slug', $slug)->value('id'), 'role' => $role, 'prominence' => $prominence];
        }

        foreach ($graph['people'] as $slug) {
            $rows[] = ['type' => 'person', 'id' => Person::query()->where('slug', $slug)->value('id'), 'role' => EntityRole::Mentioned, 'prominence' => 40];
        }

        foreach ($graph['industries'] as $slug) {
            $rows[] = ['type' => 'industry', 'id' => Industry::query()->where('slug', $slug)->value('id'), 'role' => EntityRole::Secondary, 'prominence' => 50];
        }

        foreach ($graph['countries'] ?? ['SA'] as $code) {
            $rows[] = ['type' => 'country', 'id' => Country::query()->where('code', $code)->value('id'), 'role' => EntityRole::Mentioned, 'prominence' => 30];
        }

        foreach ($graph['markets'] ?? [] as $slug) {
            $rows[] = ['type' => 'market', 'id' => Market::query()->where('slug', $slug)->value('id'), 'role' => EntityRole::Mentioned, 'prominence' => 25];
        }

        foreach ($graph['topics'] as $slug) {
            $rows[] = ['type' => 'topic', 'id' => Topic::query()->where('slug', $slug)->value('id'), 'role' => EntityRole::Mentioned, 'prominence' => 35];
        }

        return array_values(array_filter($rows, fn (array $row): bool => $row['id'] !== null));
    }

    /**
     * An opportunity that mentions Zayzafoon Sands, so its profile aggregates
     * an opportunity beside its articles and its report.
     */
    private function seedOpportunity(): void
    {
        $published = CarbonImmutable::parse('2026-10-05 09:00', config('app.timezone'));

        $opportunity = Opportunity::factory()->create([
            'slug' => 'zayzafoon-sands-maintenance-suppliers',
            'title' => 'زيزفون الرمال تفتح باب تأهيل مورّدي صيانة محطاتها',
            'summary' => 'تستقبل زيزفون الرمال طلبات التأهيل من مورّدي تنظيف الألواح الشمسية وصيانة توربينات الرياح ومراقبة الأداء عن بُعد لمحطاتها الثلاث العاملة. (زيزفون الرمال كيان تخيّلي لأغراض العرض.)',
            'opportunity_type' => 'tender',
            'industry_id' => Industry::query()->where('slug', 'energy')->value('id'),
            'country_id' => Country::query()->where('code', 'SA')->value('id'),
            'potential' => OpportunityPotential::Medium,
            'requirements' => [
                'سجل تجاري ساري وخبرة سابقة في تشغيل أو صيانة منشآت طاقة',
                'فريق ميداني قادر على العمل في مواقع المحطات خارج المدن',
                'خطة استجابة للأعطال لا تتجاوز ثمانيًا وأربعين ساعة',
            ],
            'deadline' => '2026-12-15',
            'official_source_url' => 'https://zayzafoon-sands.example/suppliers',
            'status' => 'open',
            'published_at' => $published,
        ]);

        EntityMention::query()->create([
            'mentionable_type' => $opportunity->getMorphClass(),
            'mentionable_id' => $opportunity->getKey(),
            'entity_type' => 'company',
            'entity_id' => Company::query()->where('slug', 'zayzafoon-sands')->value('id'),
            'role' => EntityRole::Primary,
            'prominence' => 100,
            'created_at' => $published,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stories(): array
    {
        $what = ArticleSection::WhatHappened->value;
        $why = ArticleSection::WhyItMatters->value;
        $who = ArticleSection::WhoIsAffected->value;
        $opp = ArticleSection::Opportunity->value;

        $zayzafoon = 'زيزفون الرمال للطاقة المتجددة';
        $fiction = ' (كيان تخيّلي لأغراض العرض)';

        return [
            [
                'slug' => 'zayzafoon-sands-first-direct-power-contract',
                'category' => 'business', 'type' => ContentType::News, 'date' => '2022-03-14 10:30',
                'title' => 'زيزفون الرمال توقّع أول عقد لبيع الكهرباء المتجددة مباشرة لعميل صناعي',
                'subtitle' => 'عقد لخمس عشرة سنة يغطي كامل إنتاج محطة بقدرة 120 ميغاواط قيد الإنشاء',
                'summary' => [
                    'وقّعت زيزفون الرمال عقدًا لبيع كامل إنتاج محطتها الأولى لعميل صناعي واحد لمدة 15 عامًا.',
                    'المحطة هجينة بقدرة 120 ميغاواط، والتشغيل التجاري مستهدف في الربع الأخير من 2023.',
                    'السعر ثابت في السنوات الخمس الأولى، ثم يُعدَّل سنويًا وفق معادلة يحددها العقد.',
                ],
                'what' => 'وقّعت زيزفون الرمال للطاقة المتجددة عقد شراء طاقة مدته 15 عامًا مع عميل صناعي في المنطقة الشرقية، تبيعه بموجبه كامل إنتاج محطتها الأولى بقدرة 120 ميغاواط.',
                'why' => 'العقد المباشر يمنح المطوّر إيرادًا مضمونًا يمكن الاقتراض بضمانه، ويمنح العميل الصناعي سعر كهرباء معروفًا لسنوات، وهما شرطان نادرًا ما يجتمعان لمطوّر بحجم زيزفون.',
                'who' => 'العملاء الصناعيون ذوو الاستهلاك الكثيف للكهرباء، والمقاولون المحليون الذين سيتولّون أعمال الإنشاء، والمموّلون الذين ينظرون في تمويل المحطة.',
                'opportunity' => 'يفتح نموذج البيع المباشر بابًا لمورّدي هياكل التثبيت وخدمات التشغيل والصيانة، إذ تحتاج كل محطة من هذا الحجم إلى عقد صيانة طويل الأجل.',
                'sections' => [
                    $what => ['عقد لخمس عشرة سنة', 'بحسب البيان الذي نشرته الشركة، يبدأ العقد مع التشغيل التجاري للمحطة، ويغطي إنتاجها الكامل دون حد أدنى للسحب. ولم تكشف الشركة اسم العميل، واكتفت بوصفه منشأة صناعية في المنطقة الشرقية.'],
                    $why => ['سعر معروف لسنوات', 'بالنسبة للعميل، يعني العقد كلفة كهرباء ثابتة لجزء كبير من استهلاكه خلال السنوات الخمس الأولى. وبالنسبة لزيزفون، يعني تدفقًا نقديًا يمكن تقديمه للمموّلين بوصفه ضمانًا، وهو ما تحتاجه شركة لم تشغّل محطة بعد.'],
                    $who => ['المقاولون أولًا', 'أول المتأثرين هم المقاولون والمورّدون المحليون الذين ستحتاجهم مرحلة الإنشاء. ويتابع العملاء الصناعيون الآخرون التجربة لمعرفة ما إذا كان النموذج قابلًا للتكرار مع منشآت أصغر.'],
                    $opp => ['عقود الصيانة الطويلة', 'تحتاج محطة بهذا الحجم إلى عقد تشغيل وصيانة طويل، وإلى مورّدين محليين لهياكل التثبيت وأنظمة المراقبة. والشركات التي تدخل هذه السلسلة مبكرًا تملك أفضلية عند تكرار النموذج.'],
                ],
                'numbers' => [
                    ['value' => '120 ميغاواط', 'label' => 'قدرة المحطة الأولى', 'source' => 'بيان زيزفون الرمال'.$fiction, 'as_of' => '2022-03'],
                    ['value' => '15 سنة', 'label' => 'مدة عقد الشراء', 'source' => 'بيان زيزفون الرمال'.$fiction, 'as_of' => '2022-03'],
                ],
                'sources' => [
                    ['title' => 'بيان صحفي: توقيع عقد شراء الطاقة الأول'.$fiction, 'publisher' => $zayzafoon, 'url' => 'https://zayzafoon-sands.example/news/2022-first-power-contract'],
                ],
                'graph' => [
                    'companies' => [['zayzafoon-sands', EntityRole::Primary, 95]],
                    'people' => ['lama-al-harbi'],
                    'industries' => ['energy', 'manufacturing'],
                    'topics' => ['renewable-energy', 'manufacturing'],
                ],
            ],
            [
                'slug' => 'durraq-payments-first-funding-round',
                'category' => 'startups', 'type' => ContentType::News, 'date' => '2023-05-09 11:00',
                'title' => 'دُرّاق للمدفوعات تجمع 18 مليون ريال لتوسيع خدماتها للمتاجر الصغيرة',
                'subtitle' => 'الجولة الأولى للشركة تموّل فريق المبيعات الميداني وتطوير التسويات اليومية',
                'summary' => [
                    'جمعت دُرّاق 18 مليون ريال في جولة تمويل أولى من مستثمرين أفراد وصندوق جريء لم تُسمّه.',
                    'تخدم المنصة نحو 2,300 متجر، وتعد التجار بتسوية مبيعاتهم في اليوم التالي.',
                    'ستذهب معظم الأموال إلى فريق المبيعات الميداني وتوسيع التغطية خارج المدن الرئيسية.',
                ],
                'what' => 'أعلنت دُرّاق للمدفوعات إغلاق جولة تمويل أولى بقيمة 18 مليون ريال، بعد عامين من إطلاق منصتها التي تقدّم أجهزة نقاط البيع وروابط الدفع للمتاجر الصغيرة.',
                'why' => 'المتاجر الصغيرة هي الشريحة التي تتأخر فيها التسويات أكثر من غيرها، والتسوية في اليوم التالي تغيّر طريقة إدارة هذه المتاجر لسيولتها اليومية.',
                'who' => 'أصحاب المتاجر الصغيرة والمتوسطة، ومزوّدو أجهزة نقاط البيع المنافسون، والمستثمرون في التقنية المالية في مراحلها المبكرة.',
                'opportunity' => 'يمكن لمورّدي البرمجيات المحاسبية للمتاجر الصغيرة الربط مع منصات الدفع الناشئة، فكل تسوية يومية هي بيانات مبيعات يحتاجها التاجر في دفاتره.',
                'sections' => [
                    $what => ['جولة أولى بعد عامين', 'قالت الشركة في بيانها إن الجولة شارك فيها مستثمرون أفراد وصندوق رأس مال جريء لم تكشف اسمه. ولم تُفصح عن تقييمها، واكتفت بالقول إن المؤسسين ما زالوا يملكون الحصة الأكبر.'],
                    $why => ['السيولة قبل النمو', 'يعاني صاحب المتجر الصغير من فجوة بين البيع وتحصيل ثمنه، وقد تمتد أيامًا. ووعد دُرّاق بالتسوية في اليوم التالي يستهدف هذه الفجوة تحديدًا، وهو ما يفسّر انتشار المنصة بين المقاهي ومحلات التجزئة.'],
                    $who => ['التاجر والمنافس', 'المستفيد المباشر هو التاجر الذي يحصل على أمواله أسرع. أما المنافسون فسيجدون أنفسهم أمام مقارنة على سرعة التسوية، لا على رسوم المعاملة وحدها.'],
                    $opp => ['الربط مع الدفاتر', 'كل عملية تسوية تولّد بيانات مبيعات منظّمة. والبرمجيات التي تحوّل هذه البيانات إلى دفاتر جاهزة للتاجر تملك فرصة شراكة مع منصات الدفع الصغيرة التي لا تبني هذه الأدوات بنفسها.'],
                ],
                'numbers' => [
                    ['value' => '18 مليون ريال', 'label' => 'قيمة الجولة الأولى', 'source' => 'بيان دُرّاق للمدفوعات'.$fiction, 'as_of' => '2023-05'],
                    ['value' => '2,300', 'label' => 'المتاجر على المنصة', 'source' => 'بيان دُرّاق للمدفوعات'.$fiction, 'as_of' => '2023-05'],
                ],
                'sources' => [
                    ['title' => 'بيان صحفي: إغلاق جولة التمويل الأولى'.$fiction, 'publisher' => 'دُرّاق للمدفوعات', 'url' => 'https://durraq-payments.example/press/first-round'],
                ],
                'graph' => [
                    'companies' => [['durraq-payments', EntityRole::Primary, 95]],
                    'people' => ['majed-al-qahtani'],
                    'industries' => ['fintech'],
                    'topics' => ['fintech', 'startups', 'venture-capital'],
                ],
            ],
            [
                'slug' => 'zayzafoon-sands-first-plant-begins-operation',
                'category' => 'saudi', 'type' => ContentType::News, 'date' => '2023-11-21 09:15',
                'title' => 'محطة زيزفون الرمال الأولى تبدأ التشغيل التجاري في موعدها',
                'subtitle' => 'محطة هجينة من الطاقة الشمسية والرياح بقدرة 120 ميغاواط تدخل الخدمة لعميل صناعي واحد',
                'summary' => [
                    'بدأت محطة زيزفون الرمال الأولى التشغيل التجاري في نوفمبر، في الموعد الذي حدّده عقد الشراء.',
                    'تجمع المحطة 100 ميغاواط من الألواح الشمسية و20 ميغاواط من توربينات الرياح.',
                    'تنتقل الشركة الآن إلى إثبات أن المحطة تنتج ما وعدت به على مدار عام كامل.',
                ],
                'what' => 'أعلنت زيزفون الرمال بدء التشغيل التجاري لمحطتها الهجينة الأولى بقدرة 120 ميغاواط في شمال المملكة، لتبدأ بذلك سنوات عقد البيع المباشر الموقّع في 2022.',
                'why' => 'التسليم في الموعد هو الاختبار الذي يحدد قدرة مطوّر صغير على الحصول على تمويل لمحطته الثانية؛ فالمموّلون يقرؤون السجل التنفيذي قبل أي شيء آخر.',
                'who' => 'العميل الصناعي الذي يبدأ الآن استلام الكهرباء، والمقاولون الذين أنهوا أعمالهم، والمموّلون الذين سيقيّمون أداء السنة الأولى.',
                'opportunity' => 'مع انتقال المحطة إلى التشغيل يبدأ عقد الصيانة الطويل، وتظهر الحاجة إلى خدمات تنظيف الألواح ومراقبة الأداء عن بُعد.',
                'sections' => [
                    $what => ['في الموعد', 'تقع المحطة في منطقة مفتوحة شمال المملكة، وتجمع حقلًا شمسيًا بقدرة 100 ميغاواط وعشر توربينات رياح بقدرة 20 ميغاواط. ويقول بيان الشركة إن الربط بشبكة العميل اكتمل قبل الموعد التعاقدي بأيام.'],
                    $why => ['السجل قبل الوعد', 'لم تكن لدى زيزفون محطة عاملة حين وقّعت عقدها الأول، فكان العقد رهانًا على قدرتها على التنفيذ. والتسليم في الموعد يحوّل هذا الرهان إلى سجل يمكن تقديمه عند تمويل المحطة التالية.'],
                    $who => ['من يبدأ الآن', 'يبدأ العميل الصناعي استلام الكهرباء بالسعر الثابت المتفق عليه. وينتقل المقاولون إلى مرحلة الضمان، بينما يبدأ المموّلون المحتملون مراقبة أرقام الإنتاج الشهرية.'],
                    $opp => ['ما بعد الإنشاء', 'التشغيل يعني عقودًا أصغر لكنها أطول: تنظيف الألواح، وصيانة التوربينات، والمراقبة عن بُعد. وهي أعمال تناسب شركات محلية صغيرة أكثر مما تناسبها مرحلة الإنشاء.'],
                ],
                'numbers' => [
                    ['value' => '100 ميغاواط', 'label' => 'القدرة الشمسية', 'source' => 'بيان زيزفون الرمال'.$fiction, 'as_of' => '2023-11'],
                    ['value' => '20 ميغاواط', 'label' => 'قدرة توربينات الرياح', 'source' => 'بيان زيزفون الرمال'.$fiction, 'as_of' => '2023-11'],
                ],
                'sources' => [
                    ['title' => 'بيان صحفي: بدء التشغيل التجاري للمحطة الأولى'.$fiction, 'publisher' => $zayzafoon, 'url' => 'https://zayzafoon-sands.example/news/2023-first-plant-online'],
                ],
                'graph' => [
                    'companies' => [['zayzafoon-sands', EntityRole::Primary, 100]],
                    'people' => ['lama-al-harbi'],
                    'industries' => ['energy'],
                    'topics' => ['renewable-energy'],
                ],
                'featured' => true,
            ],
            [
                'slug' => 'why-small-power-developers-sell-direct-to-factories',
                'category' => 'insights', 'type' => ContentType::Analysis, 'date' => '2024-04-16 08:00',
                'title' => 'لماذا يتجه مطوّرو الطاقة الصغار إلى البيع المباشر للمصانع؟',
                'subtitle' => 'قراءة في نموذجين: محطات زيزفون الرمال المستقلة وأسطح شعاع النفود',
                'summary' => [
                    'يعتمد المطوّرون الصغار على عقود البيع المباشر لأن العميل الصناعي يضمن الإيراد الذي يحتاجه المموّل.',
                    'اختارت زيزفون الرمال المحطات المستقلة، واختارت شعاع النفود أسطح المنشآت نفسها.',
                    'الفارق بين النموذجين في من يتحمّل كلفة الإنشاء ومن يملك الأرض.',
                ],
                'what' => 'في عامين، وقّعت زيزفون الرمال وشعاع النفود عقود بيع مباشر مع عملاء صناعيين بنموذجين مختلفين: الأولى تبني محطات مستقلة وتنقل الكهرباء إلى العميل، والثانية تركّب الألواح على سطح العميل نفسه.',
                'why' => 'النموذج الذي يختاره المطوّر يحدد حجم الدين الذي يحتاجه، ونوع العميل الذي يستطيع خدمته، وسرعة تكرار المشروع.',
                'who' => 'مديرو المصانع الذين يقارنون بين العرضين، والمموّلون الذين يقيّمون مخاطر كل نموذج، والمطوّرون الجدد الذين يقرّرون من أين يبدؤون.',
                'opportunity' => 'المنشآت ذات الأسطح الواسعة والاستهلاك النهاري المرتفع، كالمستودعات ومصانع التبريد، هي الأنسب لنموذج الأسطح، وهي شريحة لم يصل إليها كثيرون بعد.',
                'sections' => [
                    $what => ['نموذجان لعميل واحد', 'تشترك الشركتان في العميل المستهدف: منشأة صناعية تستهلك الكهرباء نهارًا بكميات كبيرة. لكن زيزفون تبني محطة على أرض مستقلة وتربطها بالعميل، بينما تركّب شعاع النفود الألواح على سطح المنشأة وتشغّلها مقابل رسوم شهرية.'],
                    $why => ['من يحمل الكلفة', 'في نموذج المحطة المستقلة يتحمّل المطوّر كلفة الأرض والإنشاء ويحتاج إلى دين كبير، لكنه يستطيع خدمة عميل لا يملك سطحًا كافيًا. وفي نموذج الأسطح تصغر الكلفة، لكن حجم كل مشروع محدود بمساحة السطح.'],
                    $who => ['على طاولة المصنع', 'يجد مدير المصنع نفسه أمام عرضين: عقد طويل مع محطة بعيدة، أو ألواح على سطحه دون كلفة تركيب. ويتوقف الاختيار على مساحة السطح وعلى مدة بقاء المصنع في موقعه.'],
                    $opp => ['الشريحة التي لم تُخدم', 'المستودعات الكبيرة ومصانع التبريد تجمع شرطين نادرين: أسطح واسعة واستهلاك نهاري مرتفع. وهي شريحة ما زالت خارج اهتمام معظم المطوّرين.'],
                ],
                'numbers' => [],
                'sources' => [
                    ['title' => 'صفحة نموذج العمل'.$fiction, 'publisher' => $zayzafoon, 'url' => 'https://zayzafoon-sands.example/how-we-work'],
                    ['title' => 'صفحة حلول الأسطح'.$fiction, 'publisher' => 'شعاع النفود للطاقة', 'url' => 'https://shuaa-al-nafud.example/rooftops'],
                ],
                'graph' => [
                    'companies' => [['zayzafoon-sands', EntityRole::Primary, 80], ['shuaa-al-nafud', EntityRole::Primary, 80]],
                    'people' => ['lama-al-harbi', 'khalid-al-ghamdi'],
                    'industries' => ['energy', 'manufacturing'],
                    'topics' => ['renewable-energy', 'manufacturing'],
                ],
            ],
            [
                'slug' => 'sadafat-al-qulzum-fifth-red-sea-line',
                'category' => 'business', 'type' => ContentType::News, 'date' => '2024-09-03 12:00',
                'title' => 'صدفة القلزم تضيف خطًا خامسًا بين موانئ البحر الأحمر',
                'subtitle' => 'الخط الجديد أسبوعي ويستهدف شحنات الأغذية المبرّدة الصغيرة',
                'summary' => [
                    'أطلقت صدفة القلزم خطًا أسبوعيًا خامسًا مخصصًا للحاويات المبرّدة الصغيرة.',
                    'أضافت الشركة سفينتين مستأجرتين لتشغيل الخط دون تغيير جداول خطوطها الأربعة القائمة.',
                    'من أوائل عملائه مصدّرو التمور المعالجة، ومنهم قطميرة للأغذية.',
                ],
                'what' => 'أعلنت صدفة القلزم للشحن البحري تشغيل خط أسبوعي خامس بين موانئ البحر الأحمر، مخصص للحاويات المبرّدة الصغيرة التي يحتاجها مصدّرو الأغذية.',
                'why' => 'كانت الشحنات المبرّدة الصغيرة تنتظر حتى تكتمل حمولة حاوية كاملة، والخط الأسبوعي يختصر هذا الانتظار ويقلّل الهدر في المنتجات القابلة للتلف.',
                'who' => 'مصدّرو الأغذية الصغار والمتوسطون، وشركات التخزين المبرّد قرب الموانئ، والمنافسون الذين يشغّلون خطوطًا أقل تكرارًا.',
                'opportunity' => 'انتظام الشحن الأسبوعي يجعل التصدير ممكنًا لمصانع أغذية صغيرة كانت تبيع محليًا فقط، ويخلق طلبًا على مستودعات التبريد قرب نقاط التحميل.',
                'sections' => [
                    $what => ['خط للأحجام الصغيرة', 'يستخدم الخط سفينتين مستأجرتين بسعة محدودة، ويقبل الحاويات المبرّدة غير المكتملة. وتقول الشركة إن ذلك لا يغيّر جداول خطوطها الأربعة القائمة.'],
                    $why => ['الانتظار هو الكلفة', 'في الأغذية المبرّدة، كل يوم انتظار في الميناء خسارة. والمصدّر الصغير الذي لا يملأ حاوية كاملة كان يدفع ثمن الانتظار، أو يتخلى عن التصدير.'],
                    $who => ['مصدّرو الأغذية', 'أول المستفيدين مصانع المنتجات الغذائية المتوسطة، ومنها قطميرة للأغذية التي قالت إنها ستنقل جزءًا من صادراتها إلى الخط الجديد.'],
                    $opp => ['التبريد قرب الميناء', 'كلما انتظم الشحن زاد الطلب على مساحات التبريد المؤقتة قرب نقاط التحميل. وهي استثمارات صغيرة نسبيًا مقارنة بالمستودعات اللوجستية الكبيرة.'],
                ],
                'numbers' => [
                    ['value' => '5', 'label' => 'الخطوط المنتظمة بعد الإضافة', 'source' => 'بيان صدفة القلزم'.$fiction, 'as_of' => '2024-09'],
                    ['value' => '2', 'label' => 'سفن مستأجرة للخط الجديد', 'source' => 'بيان صدفة القلزم'.$fiction, 'as_of' => '2024-09'],
                ],
                'sources' => [
                    ['title' => 'بيان صحفي: إطلاق الخط الخامس'.$fiction, 'publisher' => 'صدفة القلزم للشحن البحري', 'url' => 'https://sadafat-al-qulzum.example/news/fifth-line'],
                ],
                'graph' => [
                    'companies' => [['sadafat-al-qulzum', EntityRole::Primary, 95], ['qitmira-foods', EntityRole::Secondary, 45]],
                    'people' => ['turki-al-subaie'],
                    'industries' => ['logistics', 'food-and-agriculture'],
                    'topics' => ['logistics', 'supply-chains'],
                ],
            ],
            [
                'slug' => 'masar-report-financing-the-second-power-plant',
                'category' => 'insights', 'type' => ContentType::Report, 'date' => '2025-02-11 08:00',
                'title' => 'تقرير مسار: كيف يموّل مطوّرو الطاقة المتجددة الصغار محطتهم الثانية؟',
                'subtitle' => 'مساران لشركتين: دين مصرفي بضمان الإيراد، ومحفظة من عقود الأسطح الصغيرة',
                'summary' => [
                    'المحطة الأولى تُموَّل بعقد العميل، والثانية تُموَّل بسجل الأولى.',
                    'موّلت زيزفون الرمال توسّعها بدين مصرفي مضمون بإيرادات محطتها العاملة.',
                    'اختارت شعاع النفود تجميع عقود الأسطح الصغيرة في محفظة واحدة تُعرض على المموّلين.',
                ],
                'what' => 'يتتبع هذا التقرير كيف انتقلت شركتان صغيرتان في الطاقة المتجددة من مشروعهما الأول إلى الثاني خلال 2023 و2024، بالاعتماد على ما أفصحت عنه الشركتان وحده.',
                'why' => 'الانتقال من المحطة الأولى إلى الثانية هو المرحلة التي يتعثر فيها معظم المطوّرين الصغار، ومعرفة كيف عبرتها شركتان تختصر الطريق على من يأتي بعدهما.',
                'who' => 'المطوّرون الصغار في مرحلة المشروع الثاني، والبنوك التي تقيّم تمويل محافظ صغيرة، والعملاء الصناعيون الذين يتفاوضون على عقود طويلة.',
                'opportunity' => 'تجميع العقود الصغيرة في محفظة واحدة يفتح الباب لمموّلين لم يكونوا ليموّلوا مشروعًا منفردًا، ويخلق دورًا لمستشاري هيكلة هذه المحافظ.',
                'sections' => [
                    $what => ['شركتان ومسار واحد', 'اعتمد التقرير على بيانات الشركتين المنشورة: أرقام التشغيل الشهرية لمحطة زيزفون الأولى، وعقود الأسطح التي أعلنتها شعاع النفود. ولا يتضمن أي رقم لم تُفصح عنه الشركتان.'],
                    $why => ['عتبة المشروع الثاني', 'المحطة الأولى تُموَّل غالبًا بقوة العقد مع العميل. أما الثانية فيسأل عنها المموّل سؤالًا مختلفًا: كيف كان أداء الأولى؟ وهنا يصبح السجل التشغيلي أهم من أي وعد.'],
                    $who => ['ثلاثة أطراف', 'يهم التقرير المطوّر الذي يستعد لمشروعه الثاني، والبنك الذي يدرس تمويل محافظ صغيرة، والعميل الصناعي الذي يريد أن يعرف من سيبقى في السوق بعد عشر سنوات.'],
                    $opp => ['المحافظ الصغيرة', 'حين يجمع المطوّر عشرات العقود الصغيرة في محفظة واحدة، يصبح حجمها مناسبًا لمموّل كبير. وهيكلة هذه المحافظ عمل استشاري لم يتخصص فيه أحد بعد.'],
                ],
                'numbers' => [
                    ['value' => '120 ميغاواط', 'label' => 'محطة زيزفون الأولى العاملة', 'source' => 'إفصاحات زيزفون الرمال'.$fiction, 'as_of' => '2025-01'],
                    ['value' => '34', 'label' => 'عقد سطح في محفظة شعاع النفود', 'source' => 'إفصاحات شعاع النفود'.$fiction, 'as_of' => '2025-01'],
                ],
                'sources' => [
                    ['title' => 'تقرير التشغيل السنوي'.$fiction, 'publisher' => $zayzafoon, 'url' => 'https://zayzafoon-sands.example/reports/operations-2024'],
                    ['title' => 'قائمة عقود الأسطح'.$fiction, 'publisher' => 'شعاع النفود للطاقة', 'url' => 'https://shuaa-al-nafud.example/portfolio'],
                ],
                'graph' => [
                    'companies' => [['zayzafoon-sands', EntityRole::Primary, 90], ['shuaa-al-nafud', EntityRole::Primary, 85]],
                    'people' => ['lama-al-harbi', 'hind-al-zahrani', 'khalid-al-ghamdi'],
                    'industries' => ['energy', 'financial-services'],
                    'topics' => ['renewable-energy', 'debt-markets'],
                ],
            ],
            [
                'slug' => 'dar-al-qamariya-from-one-lodge-to-nine',
                'category' => 'stories', 'type' => ContentType::Analysis, 'date' => '2025-04-27 10:00',
                'title' => 'دار القمرية: من نُزل عائلي واحد إلى سلسلة من تسعة نُزل تراثية',
                'subtitle' => 'نموذج ضيافة يبدأ بترميم مبانٍ لا تملكها الشركة وينتهي بتوظيف سكان الحي',
                'summary' => [
                    'بدأت دار القمرية عام 2016 بنُزل واحد في مبنى عائلي مرمّم.',
                    'تدير اليوم تسعة نُزل صغيرة، لا يتجاوز أكبرها اثنتي عشرة غرفة.',
                    'يقوم نموذجها على استئجار المباني التراثية من ملّاكها وترميمها مقابل عقود طويلة.',
                ],
                'what' => 'وصلت دار القمرية للضيافة التراثية إلى تسعة نُزل صغيرة بعد تسع سنوات من افتتاح نُزلها الأول في مبنى عائلي مرمّم، دون أن تملك أيًا من المباني التي تشغّلها.',
                'why' => 'النموذج يحلّ مشكلة يعرفها ملّاك المباني التراثية: مبنى ثمين لا يملكون كلفة ترميمه ولا خبرة تشغيله، ولا يريدون بيعه.',
                'who' => 'ملّاك المباني القديمة، والحرفيون المتخصصون في الترميم، وسكان الأحياء التي تقع فيها النُزل والذين يعمل كثير منهم فيها.',
                'opportunity' => 'كل مبنى تراثي مهجور مشروع محتمل لنموذج الاستئجار والترميم، والطلب على حرفيي الترميم المؤهلين يتجاوز المعروض منهم.',
                'sections' => [
                    $what => ['تسعة نُزل بلا ملكية', 'تستأجر الشركة المبنى لمدة طويلة، وتتحمّل كلفة ترميمه، ثم تشغّله نُزلًا صغيرًا. ويحصل المالك على إيجار ثابت، ويعود إليه المبنى مرمّمًا عند نهاية العقد.'],
                    $why => ['الخيار الثالث', 'كان أمام مالك المبنى التراثي خياران: أن يتركه أو أن يبيعه. والنموذج يقدّم خيارًا ثالثًا يبقي الملكية في يده ويعيد المبنى إلى الاستخدام.'],
                    $who => ['الحي قبل الضيف', 'تقول الشركة إن معظم العاملين في نُزلها من سكان الأحياء نفسها، وهذا ما يجعل النُزل جزءًا من الحي بدل أن يكون منشأة غريبة عنه.'],
                    $opp => ['حرفة الترميم', 'أكبر قيد على توسّع الشركة ليس التمويل بل الحرفيون: النجّارون والبنّاؤون الذين يعرفون مواد البناء التقليدية. والتدريب على هذه الحرف فرصة قائمة بذاتها.'],
                ],
                'numbers' => [
                    ['value' => '9', 'label' => 'النُزل العاملة', 'source' => 'بيان دار القمرية'.$fiction, 'as_of' => '2025-04'],
                    ['value' => '12', 'label' => 'غرف أكبر نُزل', 'source' => 'بيان دار القمرية'.$fiction, 'as_of' => '2025-04'],
                ],
                'sources' => [
                    ['title' => 'صفحة الشركة: قصتنا'.$fiction, 'publisher' => 'دار القمرية للضيافة التراثية', 'url' => 'https://dar-al-qamariya.example/our-story'],
                ],
                'graph' => [
                    'companies' => [['dar-al-qamariya', EntityRole::Primary, 100]],
                    'people' => ['nouf-al-shehri'],
                    'industries' => ['tourism'],
                    'topics' => ['tourism', 'entrepreneurship'],
                ],
                'featured' => true,
            ],
            [
                'slug' => 'zayzafoon-sands-green-sukuk-private-placement',
                'category' => 'saudi', 'type' => ContentType::News, 'date' => '2025-08-19 13:30',
                'title' => 'زيزفون الرمال تجمع 300 مليون ريال عبر صكوك خضراء بطرح خاص',
                'subtitle' => 'الإصدار يموّل محطتين جديدتين ويُسدَّد من إيرادات عقود البيع المباشر',
                'summary' => [
                    'أصدرت زيزفون الرمال صكوكًا خضراء بقيمة 300 مليون ريال طُرحت طرحًا خاصًا على مستثمرين مؤسسيين.',
                    'أجل الصكوك سبع سنوات، وتُسدَّد من إيرادات عقود البيع المباشر.',
                    'تموّل الحصيلة محطتين ترفعان القدرة المركّبة للشركة إلى 420 ميغاواط.',
                ],
                'what' => 'أعلنت زيزفون الرمال إتمام إصدار صكوك خضراء بقيمة 300 مليون ريال لأجل سبع سنوات، طُرحت طرحًا خاصًا على عدد محدود من المستثمرين المؤسسيين.',
                'why' => 'الانتقال من الدين المصرفي إلى الصكوك يعني أن المطوّر صار قادرًا على الاقتراض بضمان إيراداته التعاقدية لا أصوله فقط، وهي خطوة نادرة لشركة بهذا الحجم.',
                'who' => 'المستثمرون المؤسسيون الباحثون عن أدوات دخل مرتبطة بالطاقة المتجددة، والمطوّرون الصغار الذين يراقبون إمكانية تكرار التجربة، والعملاء الذين ستغذّيهم المحطتان الجديدتان.',
                'opportunity' => 'نجاح طرح خاص بهذا الحجم يجعل الصكوك الخضراء خيارًا مطروحًا أمام مطوّرين آخرين يملكون عقودًا طويلة، ويفتح عملًا لمكاتب هيكلة هذه الإصدارات.',
                'sections' => [
                    $what => ['إصدار بطرح خاص', 'قالت الشركة إن الإصدار غُطّي بالكامل، وإن حصيلته مخصصة لمحطتين جديدتين بقدرة إجمالية 300 ميغاواط. ولم تُفصح عن عائد الصكوك.'],
                    $why => ['الإيراد ضمانًا', 'ما يميّز الإصدار أن سداده مرتبط بإيرادات عقود البيع المباشر لا بأصول الشركة وحدها. وهذا يتطلب سجلًا تشغيليًا يثبت انتظام الإيرادات، وهو ما بنته المحطة الأولى منذ 2023.'],
                    $who => ['مستثمرون ومطوّرون', 'يقدّم الإصدار أداة دخل جديدة للمستثمرين المؤسسيين، ويضع أمام المطوّرين الآخرين مثالًا على ما يمكن تمويله حين يكتمل السجل.'],
                    $opp => ['ما بعد الإصدار الأول', 'الإصدار الأول هو الأصعب لأنه يبني الهيكل من الصفر. والإصدارات اللاحقة، لهذه الشركة أو لغيرها، ستكون أسرع وأقل كلفة، وهنا فرصة مكاتب الهيكلة.'],
                ],
                'numbers' => [
                    ['value' => '300 مليون ريال', 'label' => 'قيمة الإصدار', 'source' => 'بيان زيزفون الرمال'.$fiction, 'as_of' => '2025-08'],
                    ['value' => '7 سنوات', 'label' => 'أجل الصكوك', 'source' => 'بيان زيزفون الرمال'.$fiction, 'as_of' => '2025-08'],
                    ['value' => '420 ميغاواط', 'label' => 'القدرة بعد المحطتين', 'source' => 'بيان زيزفون الرمال'.$fiction, 'as_of' => '2025-08'],
                ],
                'sources' => [
                    ['title' => 'بيان صحفي: إتمام إصدار الصكوك الخضراء'.$fiction, 'publisher' => $zayzafoon, 'url' => 'https://zayzafoon-sands.example/news/2025-green-sukuk'],
                ],
                'graph' => [
                    'companies' => [['zayzafoon-sands', EntityRole::Primary, 100]],
                    'people' => ['hind-al-zahrani'],
                    'industries' => ['energy', 'financial-services'],
                    'markets' => ['sukuk-and-bonds'],
                    'topics' => ['debt-markets', 'renewable-energy', 'capital-markets'],
                ],
                'featured' => true,
            ],
            [
                'slug' => 'sadafat-al-qulzum-opens-djibouti-office',
                'category' => 'global', 'type' => ContentType::News, 'date' => '2026-01-25 10:45',
                'title' => 'صدفة القلزم تفتح مكتبًا في جيبوتي لإدارة خطها السادس',
                'subtitle' => 'الخط يربط موانئ البحر الأحمر في المملكة بالساحل الأفريقي المقابل مرتين شهريًا',
                'summary' => [
                    'افتتحت صدفة القلزم مكتبًا في جيبوتي لإدارة خطها المنتظم السادس.',
                    'يربط الخط موانئ البحر الأحمر في المملكة بموانئ القرن الأفريقي مرتين في الشهر.',
                    'يستهدف الخط مصدّري مواد البناء والأغذية السعوديين إلى أسواق القرن الأفريقي.',
                ],
                'what' => 'أعلنت صدفة القلزم للشحن البحري افتتاح مكتب في جيبوتي لإدارة خطها المنتظم السادس، الذي يربط موانئ البحر الأحمر في المملكة بموانئ القرن الأفريقي مرتين في الشهر.',
                'why' => 'أسواق القرن الأفريقي قريبة جغرافيًا لكنها بعيدة لوجستيًا عن المصدّر السعودي الصغير، والخط المنتظم يحوّل هذا القرب إلى طريق تجاري فعلي.',
                'who' => 'مصدّرو مواد البناء والأغذية في المملكة، ووكلاء الشحن في الموانئ الأفريقية، والمستوردون على الضفة المقابلة.',
                'opportunity' => 'الخط المنتظم يجعل التصدير إلى القرن الأفريقي قابلًا للتخطيط، وهو ما يحتاجه المصنّع الصغير قبل أن يغامر بسوق جديدة.',
                'sections' => [
                    $what => ['مكتب وخط سادس', 'قالت الشركة إن المكتب سيتولى الحجز والتخليص على الجانب الأفريقي، وإن الخط يعمل بسفينة واحدة في هذه المرحلة. وبذلك يصل عدد خطوطها المنتظمة إلى ستة.'],
                    $why => ['القرب لا يكفي', 'المسافة بين ضفتي البحر الأحمر قصيرة، لكن غياب الخطوط المنتظمة كان يجعل الشحن عبرها غير متوقع. والمصدّر لا يبني علاقة مع مستورد إذا لم يعرف متى تصل بضاعته.'],
                    $who => ['على الضفتين', 'على الجانب السعودي يستفيد مصدّرو مواد البناء والأغذية، وعلى الجانب الآخر يحصل المستوردون على خيار شحن مباشر بدل المرور بموانئ وسيطة.'],
                    $opp => ['سوق قريبة', 'قد تكون أسواق القرن الأفريقي أقرب طريق إلى التصدير الأول للمصنّع السعودي الصغير، والخط المنتظم هو الشرط الذي كان ينقص هذا الطريق.'],
                ],
                'numbers' => [
                    ['value' => '6', 'label' => 'الخطوط المنتظمة', 'source' => 'بيان صدفة القلزم'.$fiction, 'as_of' => '2026-01'],
                    ['value' => '2', 'label' => 'رحلات شهرية على الخط الجديد', 'source' => 'بيان صدفة القلزم'.$fiction, 'as_of' => '2026-01'],
                ],
                'sources' => [
                    ['title' => 'بيان صحفي: افتتاح مكتب جيبوتي'.$fiction, 'publisher' => 'صدفة القلزم للشحن البحري', 'url' => 'https://sadafat-al-qulzum.example/news/djibouti-office'],
                ],
                'graph' => [
                    'companies' => [['sadafat-al-qulzum', EntityRole::Primary, 100]],
                    'people' => ['turki-al-subaie'],
                    'industries' => ['logistics'],
                    'topics' => ['logistics', 'supply-chains'],
                ],
            ],
            [
                'slug' => 'zayzafoon-sands-adds-battery-storage',
                'category' => 'business', 'type' => ContentType::News, 'date' => '2026-06-08 09:30',
                'title' => 'زيزفون الرمال تضيف تخزين البطاريات إلى محطاتها الثلاث',
                'subtitle' => 'الشركة تمدّ ساعات التوريد إلى المساء لعملاء يعملون بنظام الورديتين',
                'summary' => [
                    'أقرّ مجلس إدارة زيزفون الرمال إضافة أنظمة تخزين بالبطاريات إلى محطاتها الثلاث العاملة.',
                    'بلغت القدرة المركّبة للشركة 420 ميغاواط بعد تشغيل محطتيها الجديدتين.',
                    'يتيح التخزين بيع الكهرباء في ساعات المساء، حين يعمل كثير من العملاء الصناعيين بوردية ثانية.',
                ],
                'what' => 'أعلنت زيزفون الرمال أن مجلس إدارتها أقرّ إضافة أنظمة تخزين بالبطاريات إلى محطاتها الثلاث، بعد اكتمال تشغيل المحطتين اللتين موّلتهما صكوك 2025.',
                'why' => 'الكهرباء الشمسية تُنتَج نهارًا، وكثير من المصانع تعمل بورديتين؛ والتخزين هو ما يسمح للمطوّر بخدمة الوردية الثانية بدل ترك هذا الطلب لمصادر أخرى.',
                'who' => 'العملاء الصناعيون العاملون بنظام الورديتين، ومورّدو أنظمة التخزين، والمطوّرون المنافسون الذين ما زالوا يبيعون الإنتاج النهاري وحده.',
                'opportunity' => 'إضافة التخزين إلى محطات قائمة سوق مختلفة عن بناء المحطات، وتحتاج إلى خبرات تكامل وصيانة ما زالت نادرة محليًا.',
                'sections' => [
                    $what => ['ثلاث محطات وبطاريات', 'قالت الشركة إن أنظمة التخزين ستُضاف على مراحل، بدءًا بالمحطة الأولى العاملة منذ 2023. ولم تُفصح عن كلفة المشروع ولا عن سعة التخزين المستهدفة.'],
                    $why => ['الوردية الثانية', 'يقتصر إنتاج الألواح الشمسية على النهار، بينما يمتد استهلاك المصانع ذات الورديتين إلى المساء. والتخزين يحوّل جزءًا من إنتاج الظهيرة إلى كهرباء تُباع بعد الغروب.'],
                    $who => ['العميل والمنافس', 'يحصل العميل الصناعي على تغطية أطول من مصدر واحد بسعر متفق عليه. أما المطوّرون الذين يبيعون الإنتاج النهاري وحده فسيجدون عرضهم أضيق أمام هذا العرض.'],
                    $opp => ['التكامل لا التصنيع', 'الفرصة الأقرب ليست في تصنيع البطاريات بل في تكاملها مع المحطات القائمة وصيانتها، وهي خبرات ما زالت تُستقدم من الخارج في معظم المشاريع.'],
                ],
                'numbers' => [
                    ['value' => '420 ميغاواط', 'label' => 'القدرة المركّبة', 'source' => 'بيان زيزفون الرمال'.$fiction, 'as_of' => '2026-06'],
                    ['value' => '3', 'label' => 'المحطات العاملة', 'source' => 'بيان زيزفون الرمال'.$fiction, 'as_of' => '2026-06'],
                ],
                'sources' => [
                    ['title' => 'بيان صحفي: قرار مجلس الإدارة بشأن التخزين'.$fiction, 'publisher' => $zayzafoon, 'url' => 'https://zayzafoon-sands.example/news/2026-battery-storage'],
                ],
                'graph' => [
                    'companies' => [['zayzafoon-sands', EntityRole::Primary, 100]],
                    'people' => ['lama-al-harbi'],
                    'industries' => ['energy'],
                    'topics' => ['renewable-energy', 'manufacturing'],
                ],
                'featured' => true,
            ],
            [
                'slug' => 'durraq-payments-launches-merchant-financing',
                'category' => 'startups', 'type' => ContentType::News, 'date' => '2026-10-12 11:00',
                'title' => 'دُرّاق للمدفوعات تطلق تمويلًا قصير الأجل يُسدَّد من مبيعات التاجر',
                'subtitle' => 'المنتج الجديد يستند إلى سجل المبيعات الذي تمرّ تسوياته عبر المنصة',
                'summary' => [
                    'أطلقت دُرّاق تمويلًا قصير الأجل للتجار يُسدَّد بنسبة ثابتة من مبيعاتهم اليومية.',
                    'تتجاوز المدفوعات التي تمرّ عبر المنصة شهريًا 1.4 مليار ريال.',
                    'يقتصر المنتج في مرحلته الأولى على التجار الذين أمضوا اثني عشر شهرًا على المنصة.',
                ],
                'what' => 'أعلنت دُرّاق للمدفوعات إطلاق منتج تمويل قصير الأجل للتجار، يُسدَّد بنسبة ثابتة من مبيعاتهم اليومية، بالشراكة مع جهة تمويل مرخّصة لم تكشف الشركة اسمها.',
                'why' => 'المنصة ترى مبيعات التاجر يومًا بيوم، وهذه رؤية لا يملكها المموّل التقليدي؛ وتحويلها إلى قرار تمويل هو ما يميّز منصات الدفع في خدمة المتاجر الصغيرة.',
                'who' => 'التجار الذين يحتاجون تمويلًا لمخزون موسمي، والبنوك التي تخسر هذه الشريحة لصالح المنصات، والمنافسون من منصات الدفع.',
                'opportunity' => 'تمويل المخزون الموسمي للمتاجر الصغيرة شريحة لم تُخدم جيدًا، والمنصات التي تملك بيانات المبيعات هي الأقدر على دخولها.',
                'sections' => [
                    $what => ['تمويل من المبيعات', 'يحدد المنتج سقف التمويل بحسب متوسط مبيعات التاجر في الأشهر الماضية، ويقتطع السداد تلقائيًا من التسويات اليومية. وتقول الشركة إن التمويل يقدّمه شريك مرخّص، وإن دورها تقديم البيانات وإدارة التحصيل.'],
                    $why => ['البيانات قبل الضمان', 'يطلب المموّل التقليدي ضمانات لا يملكها صاحب المتجر الصغير. أما المنصة فتعرف إيقاع مبيعاته بالتفصيل، وتستطيع ربط السداد بقدرته الفعلية على الدفع.'],
                    $who => ['التاجر الموسمي', 'أكثر المستفيدين تجار المواسم الذين يحتاجون مخزونًا قبل الذروة بأسابيع. ويجد البنك نفسه أمام منافس يعرف عميله أكثر منه.'],
                    $opp => ['الشريحة الموسمية', 'تمويل المخزون الموسمي للمتاجر الصغيرة شريحة صغيرة المبالغ كثيرة العدد، ولهذا تجاهلها التمويل التقليدي. والبيانات هي ما يجعلها مجدية.'],
                ],
                'numbers' => [
                    ['value' => '1.4 مليار ريال', 'label' => 'المدفوعات الشهرية عبر المنصة', 'source' => 'بيان دُرّاق للمدفوعات'.$fiction, 'as_of' => '2026-10'],
                    ['value' => '12 شهرًا', 'label' => 'الحد الأدنى لعمر التاجر على المنصة', 'source' => 'بيان دُرّاق للمدفوعات'.$fiction, 'as_of' => '2026-10'],
                ],
                'sources' => [
                    ['title' => 'بيان صحفي: إطلاق تمويل التجار'.$fiction, 'publisher' => 'دُرّاق للمدفوعات', 'url' => 'https://durraq-payments.example/press/merchant-financing'],
                ],
                'graph' => [
                    'companies' => [['durraq-payments', EntityRole::Primary, 100]],
                    'people' => ['majed-al-qahtani'],
                    'industries' => ['fintech', 'retail'],
                    'topics' => ['fintech', 'startups'],
                ],
                'featured' => true,
            ],
        ];
    }
}
