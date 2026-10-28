<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The structured half of the content graph in every edition: section names,
 * topic names, menu labels and homepage section titles.
 *
 * Additive and idempotent. It fills a language only where that language is
 * missing and never touches a value someone already wrote, so running it over
 * an edited database adds and does not revert. Keyed on the Arabic text, so
 * an Arabic label it does not know is left for an editor, not guessed.
 *
 * Editorial content (articles) is never created here: an edition's stories
 * are written or translated through the workflow, behind the publish gate.
 */
class EditionLabelsSeeder extends Seeder
{
    /** Arabic → [en, fr, hi, ur-pk, zh-hans]. */
    private const LABELS = [
        // Sections
        'السعودية والأسواق' => ['Saudi Arabia & markets', 'Arabie saoudite et marchés', 'सऊदी अरब और बाज़ार', 'سعودی عرب اور مارکیٹس', '沙特与市场'],
        'الأعمال والشركات' => ['Business & companies', 'Entreprises', 'कारोबार और कंपनियाँ', 'کاروبار اور کمپنیاں', '商业与企业'],
        'رؤى وفرص' => ['Insights & opportunities', 'Analyses et opportunités', 'अंतर्दृष्टि और अवसर', 'بصیرت اور مواقع', '洞察与机遇'],
        'الشركات الناشئة وريادة الأعمال' => ['Startups & entrepreneurship', 'Start-up et entrepreneuriat', 'स्टार्टअप और उद्यमिता', 'اسٹارٹ اپس اور کاروباری صلاحیت', '初创企业与创业'],
        'عالمي بعدسة سعودية' => ['Global, through a Saudi lens', 'Le monde, vu d’Arabie saoudite', 'सऊदी नज़रिए से दुनिया', 'سعودی نظر سے دنیا', '沙特视角看全球'],
        'قصص نجاح' => ['Success stories', 'Histoires de réussite', 'सफलता की कहानियाँ', 'کامیابی کی کہانیاں', '成功故事'],
        // Footer platform menu
        'عن مسار' => ['About MASAR', 'À propos de MASAR', 'मसार के बारे में', 'مسار کے بارے میں', '关于 MASAR'],
        'فريق التحرير' => ['The editorial team', 'L’équipe de rédaction', 'संपादकीय टीम', 'ادارتی ٹیم', '编辑团队'],
        'سياسة التصحيح' => ['Corrections policy', 'Politique de correction', 'सुधार नीति', 'درستی کی پالیسی', '更正政策'],
        'أعلن معنا' => ['Advertise with us', 'Annoncer chez nous', 'हमारे साथ विज्ञापन दें', 'ہمارے ساتھ اشتہار دیں', '广告合作'],
        'اتصل بنا' => ['Contact us', 'Nous contacter', 'संपर्क करें', 'ہم سے رابطہ کریں', '联系我们'],
        // Homepage section titles
        'القصة الكبرى' => ['The big story', 'Le grand sujet', 'बड़ी ख़बर', 'بڑی خبر', '头条故事'],
        'استكشف مسار' => ['Explore MASAR', 'Explorer MASAR', 'मसार देखें', 'مسار دیکھیں', '探索 MASAR'],
        'الأبرز' => ['Top stories', 'À la une', 'प्रमुख ख़बरें', 'نمایاں خبریں', '要闻'],
        'السعودية' => ['Saudi Arabia', 'Arabie saoudite', 'सऊदी अरब', 'سعودی عرب', '沙特'],
        'الأسواق' => ['Markets', 'Marchés', 'बाज़ार', 'مارکیٹس', '市场'],
        'فرص' => ['Opportunities', 'Opportunités', 'अवसर', 'مواقع', '机遇'],
        'مسار الاستخباري' => ['MASAR Intelligence', 'MASAR Intelligence', 'मसार इंटेलिजेंस', 'مسار انٹیلی جنس', 'MASAR 情报'],
        'رؤى وتحليلات' => ['Insights & analysis', 'Analyses et décryptages', 'अंतर्दृष्टि और विश्लेषण', 'بصیرت اور تجزیے', '洞察与分析'],
        'الأكثر قراءة' => ['Most read', 'Les plus lus', 'सबसे ज़्यादा पढ़ी गई', 'سب سے زیادہ پڑھی گئی', '最多阅读'],
        'اختيارات المحرر' => ['Editor’s picks', 'Choix de la rédaction', 'संपादक की पसंद', 'مدیر کا انتخاب', '编辑推荐'],
        'مسار مرئي' => ['MASAR video', 'MASAR vidéo', 'मसार वीडियो', 'مسار ویڈیو', 'MASAR 视频'],
        'تقارير' => ['Reports', 'Rapports', 'रिपोर्टें', 'رپورٹس', '报告'],
        'بيانات' => ['Data', 'Données', 'डेटा', 'ڈیٹا', '数据'],
        'عدد مسار 001' => ['MASAR issue 001', 'MASAR, numéro 001', 'मसार अंक 001', 'مسار شمارہ 001', 'MASAR 第 001 期'],
        'شركات في الأخبار' => ['Companies in the news', 'Entreprises dans l’actualité', 'ख़बरों में कंपनियाँ', 'خبروں میں کمپنیاں', '新闻中的企业'],
        'النشرة البريدية' => ['The newsletter', 'La lettre d’information', 'न्यूज़लेटर', 'نیوز لیٹر', '电子通讯'],
        // Topics
        'رؤية 2030' => ['Vision 2030', 'Vision 2030', 'विज़न 2030', 'ویژن 2030', '2030愿景'],
        'الاستثمار الأجنبي' => ['Foreign investment', 'Investissement étranger', 'विदेशी निवेश', 'غیر ملکی سرمایہ کاری', '外国投资'],
        'التقنية المالية' => ['Fintech', 'Fintech', 'फ़िनटेक', 'فن ٹیک', '金融科技'],
        'الطاقة المتجددة' => ['Renewable energy', 'Énergies renouvelables', 'नवीकरणीय ऊर्जा', 'قابلِ تجدید توانائی', '可再生能源'],
        'سلاسل الإمداد' => ['Supply chains', 'Chaînes d’approvisionnement', 'आपूर्ति शृंखलाएँ', 'سپلائی چینز', '供应链'],
        'التحول الرقمي' => ['Digital transformation', 'Transformation numérique', 'डिजिटल रूपांतरण', 'ڈیجیٹل تبدیلی', '数字化转型'],
        'سوق العقار' => ['Real estate', 'Immobilier', 'रियल एस्टेट', 'رئیل اسٹیٹ', '房地产'],
        'الشركات الناشئة' => ['Startups', 'Start-up', 'स्टार्टअप', 'اسٹارٹ اپس', '初创企业'],
        'الاندماج والاستحواذ' => ['Mergers & acquisitions', 'Fusions et acquisitions', 'विलय और अधिग्रहण', 'انضمام اور حصول', '并购'],
        'أسواق الدين' => ['Debt markets', 'Marchés obligataires', 'ऋण बाज़ार', 'قرض کی مارکیٹس', '债务市场'],
        'الذكاء الاصطناعي' => ['Artificial intelligence', 'Intelligence artificielle', 'कृत्रिम बुद्धिमत्ता', 'مصنوعی ذہانت', '人工智能'],
        'التعدين' => ['Mining', 'Mines', 'खनन', 'کان کنی', '矿业'],
        'السياحة' => ['Tourism', 'Tourisme', 'पर्यटन', 'سیاحت', '旅游'],
        'الخدمات اللوجستية' => ['Logistics', 'Logistique', 'लॉजिस्टिक्स', 'لاجسٹکس', '物流'],
        'التجزئة' => ['Retail', 'Commerce de détail', 'खुदरा', 'ریٹیل', '零售'],
        'الصناعات التحويلية' => ['Manufacturing', 'Industrie manufacturière', 'विनिर्माण', 'مینوفیکچرنگ', '制造业'],
        'رأس المال الجريء' => ['Venture capital', 'Capital-risque', 'वेंचर कैपिटल', 'وینچر کیپیٹل', '风险投资'],
        'الحوكمة' => ['Governance', 'Gouvernance', 'शासन', 'گورننس', '公司治理'],
        'أسواق المال' => ['Capital markets', 'Marchés de capitaux', 'पूंजी बाज़ार', 'کیپیٹل مارکیٹس', '资本市场'],
        'ريادة الأعمال' => ['Entrepreneurship', 'Entrepreneuriat', 'उद्यमिता', 'کاروباری صلاحیت', '创业'],
    ];

    /**
     * Navigation wants the short form, as the existing English labels are
     * ("Saudi", "Business"). The full section names in French measured 402px
     * past a 1280px viewport in the main menu (i18n audit, 2026-10-28).
     */
    private const MENU = [
        'السعودية والأسواق' => ['Saudi', 'Arabie saoudite', 'सऊदी', 'سعودی', '沙特'],
        'الأعمال والشركات' => ['Business', 'Entreprises', 'कारोबार', 'کاروبار', '商业'],
        'رؤى وفرص' => ['Insights', 'Analyses', 'अंतर्दृष्टि', 'بصیرت', '洞察'],
        'الشركات الناشئة وريادة الأعمال' => ['Startups', 'Start-up', 'स्टार्टअप', 'اسٹارٹ اپس', '初创'],
        'عالمي بعدسة سعودية' => ['Global', 'Monde', 'विश्व', 'عالمی', '全球'],
        'قصص نجاح' => ['Stories', 'Réussites', 'सफलताएँ', 'کامیابیاں', '成功故事'],
    ];

    private const CODES = ['en', 'fr', 'hi', 'ur-pk', 'zh-hans'];

    public function run(): void
    {
        $this->translationRows('category_translations', 'category_id');
        $this->translationRows('topic_translations', 'topic_id');
        $this->jsonColumn('menu_items', 'label', self::MENU);
        $this->jsonColumn('homepage_sections', 'title');
    }

    /** One row per locale beside the Arabic one; existing rows are left alone. */
    private function translationRows(string $table, string $key): void
    {
        foreach (DB::table($table)->where('locale', 'ar')->get() as $ar) {
            $tr = self::LABELS[$ar->name] ?? null;
            if ($tr === null) {
                continue;
            }

            foreach (self::CODES as $i => $code) {
                DB::table($table)->insertOrIgnore([$key => $ar->{$key}, 'locale' => $code, 'name' => $tr[$i]]);
            }
        }
    }

    /**
     * A JSON column of locale => text. Absent locales are filled; a present
     * one is replaced only when it is exactly the long label this seeder
     * itself wrote before the short menu forms existed — never a person's.
     *
     * @param  array<string, list<string>>  $preferred
     */
    private function jsonColumn(string $table, string $column, array $preferred = []): void
    {
        foreach (DB::table($table)->get(['id', $column]) as $row) {
            $values = json_decode((string) $row->{$column}, true) ?: [];
            $ar = $values['ar'] ?? '';
            $tr = $preferred[$ar] ?? self::LABELS[$ar] ?? null;
            if ($tr === null) {
                continue;
            }

            $filled = $values;
            foreach (self::CODES as $i => $code) {
                $ours = self::LABELS[$ar][$i] ?? null;
                if (! isset($filled[$code]) || ($filled[$code] === $ours && $ours !== $tr[$i])) {
                    $filled[$code] = $tr[$i];
                }
            }

            if ($filled !== $values) {
                DB::table($table)->where('id', $row->id)->update([$column => json_encode($filled, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }
}
