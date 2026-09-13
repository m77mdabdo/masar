<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * The photography library, described by hand.
 *
 * Alt text is written from looking at each frame, not from the filename: the
 * publish gate requires alt text, and a screen reader given
 * "pexels-tomfisk-10396412" learns nothing. Credits keep the photographer's
 * published Latin handle rather than an invented Arabic spelling of a real
 * person's name.
 *
 * `subjects` is what the picture is of, used to keep a refinery off a heritage
 * story. `orientation` decides which crops a frame survives — a 4160x6240
 * portrait cannot fill a 16:9 hero without losing most of the building.
 */
final class ImageCatalogue
{
    /**
     * @return array<int, array{file: string, alt: string, credit: string, subjects: array<int, string>, orientation: string}>
     */
    public static function entries(): array
    {
        return [
            // ---- Riyadh -------------------------------------------------
            [
                'file' => 'pexels-aburhman187-29859597.jpg',
                'alt' => 'أبراج مركز الملك عبدالله المالي في الرياض عند ضوء النهار، ويظهر في المقدّمة صفّ من دراجات التأجير المشترك.',
                'credit' => 'Abu Rhman / Pexels',
                'subjects' => ['riyadh', 'business'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'glenov-brankovic-flsXgoPoIuY-unsplash.jpg',
                'alt' => 'برج المملكة في الرياض ليلاً، وتمتدّ أسفله خطوط ضوء السيارات على الطريق السريع.',
                'credit' => 'Glenov Brankovic / Unsplash',
                'subjects' => ['riyadh'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'saifaldhaher-vAkHAP27QMk-unsplash (1).jpg',
                'alt' => 'أفق حيّ العليا في الرياض ليلاً، تتوسّطه أبراج مضاءة وتمتدّ خلفه أضواء المدينة حتى الأفق.',
                'credit' => 'Saif Aldhaher / Unsplash',
                'subjects' => ['riyadh'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'mohammed-alqarni-XBRJXgXYJY4-unsplash.jpg',
                'alt' => 'أبراج مركز الملك عبدالله المالي في الرياض تحت غيوم منخفضة، ويمرّ أمامها جسر قطار الرياض وصفّ من النخيل.',
                'credit' => 'Mohammed Alqarni / Unsplash',
                'subjects' => ['riyadh'],
                'orientation' => 'portrait',
            ],

            // ---- Markets and workplaces ---------------------------------
            [
                'file' => 'pexels-alesiakozik-6781273.jpg',
                'alt' => 'شاشة تداول تعرض رسماً بيانياً بالشموع اليابانية لحركة الأسعار خلال جلسة.',
                'credit' => 'Alesia Kozik / Pexels',
                'subjects' => ['markets'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-tima-miroshnichenko-5717315.jpg',
                'alt' => 'قاعة اجتماعات بطاولة طويلة ومقاعد جلدية، تطلّ نوافذها الممتدّة على أبراج المدينة.',
                'credit' => 'Tima Miroshnichenko / Pexels',
                'subjects' => ['business'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-hristo-sahatchiev-273072-821357.jpg',
                'alt' => 'مكتب عمل بحاسوب محمول بجانب نافذة واسعة تطلّ على سلسلة جبلية.',
                'credit' => 'Hristo Sahatchiev / Pexels',
                'subjects' => ['business'],
                'orientation' => 'landscape',
            ],

            // ---- Energy -------------------------------------------------
            [
                'file' => 'pexels-tomfisk-10396412.jpg',
                'alt' => 'لقطة جوية لمصفاة تكرير مضاءة عند الغسق تظهر فيها أبراج التقطير وشبكة خطوط الأنابيب.',
                'credit' => 'Tom Fisk / Pexels',
                'subjects' => ['energy', 'industry'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-diego-f-parra-33199-24244231.jpg',
                'alt' => 'ناقلة غاز طبيعي مسال ترسو عند محطة تصدير، وتظهر خلفها خزانات كروية وأذرع التحميل.',
                'credit' => 'Diego F. Parra / Pexels',
                'subjects' => ['energy', 'industry'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-kindelmedia-9800092.jpg',
                'alt' => 'صفوف من الألواح الشمسية تمتدّ أمام عنفات رياح في محطة طاقة متجددة.',
                'credit' => 'Kindel Media / Pexels',
                'subjects' => ['energy'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-alfomedeiros-15268778.jpg',
                'alt' => 'عنفات رياح بيضاء تدور في حقل مفتوح تحت سماء زرقاء صافية.',
                'credit' => 'Alfo Medeiros / Pexels',
                'subjects' => ['energy'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-pam-santos-2153999487-37274707.jpg',
                'alt' => 'خمس عنفات رياح على تلّ عشبي عند الغسق، وتمتدّ أمامها أرض مكشوفة ذهبية اللون.',
                'credit' => 'Pam Santos / Pexels',
                'subjects' => ['energy'],
                'orientation' => 'portrait',
            ],

            // ---- Industry -----------------------------------------------
            [
                'file' => 'pexels-igor-passchier-111147847-32399139.jpg',
                'alt' => 'مصنع بتروكيماويات مضاء عند الزرقة الليلية، تظهر فيه أعمدة التقطير وخزان أفقي كبير.',
                'credit' => 'Igor Passchier / Pexels',
                'subjects' => ['industry', 'energy'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-introspectivedsgn-9407367.jpg',
                'alt' => 'لقطة جوية لمجمّع صناعي ساحلي تظهر فيه خزانات التخزين الدائرية وشبكة الطرق الداخلية.',
                'credit' => 'Introspective Design / Pexels',
                'subjects' => ['industry'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-riicardol-15253494.jpg',
                'alt' => 'لقطة جوية لمصفاة تكرير تظهر فيها شبكة الأنابيب وأعمدة التقطير وخزّانات التخزين.',
                'credit' => 'Ricardo L / Pexels',
                'subjects' => ['industry'],
                'orientation' => 'portrait',
            ],

            // ---- Real estate and cities ---------------------------------
            [
                'file' => 'pexels-sharrrrrk-15961226.jpg',
                'alt' => 'برج سكني حديث عند الغسق تنعكس على واجهته الزجاجية ألوان السماء.',
                'credit' => 'Sharrrrrk / Pexels',
                'subjects' => ['realestate', 'business'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-raziuddin-farooqi-168235081-10961006.jpg',
                'alt' => 'سلسلة جبلية عند الغروب تطلّ على مدينة تمتدّ مبانيها في السهل أسفلها.',
                'credit' => 'Raziuddin Farooqi / Pexels',
                'subjects' => ['regions'],
                'orientation' => 'landscape',
            ],

            // ---- Coast, tourism, leisure --------------------------------
            [
                'file' => 'pexels-irfan-rahat-164426592-12771547.jpg',
                'alt' => 'ألعاب نارية فوق كورنيش جدة، ويظهر تحتها مسار السباق المضاء والواجهة البحرية.',
                'credit' => 'Irfan Rahat / Pexels',
                'subjects' => ['tourism'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-marjan-147528816-10483826.jpg',
                'alt' => 'لقطة جوية لواجهة بحرية مضاءة تتصاعد فوقها الألعاب النارية أمام منتجع ساحلي.',
                'credit' => 'Marjan Blan / Pexels',
                'subjects' => ['tourism'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-marjan-147528816-10483837.jpg',
                'alt' => 'عروض ألعاب نارية تضيء خليجاً ساحلياً، ويمتدّ أسفلها طريق كورنيش تحفّه الفنادق والنخيل.',
                'credit' => 'Marjan Blan / Pexels',
                'subjects' => ['tourism'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-iosamah787-14989817.jpg',
                'alt' => 'لقطة جوية لحديقة على الواجهة البحرية يمتدّ منها ممشى خشبي داخل الماء.',
                'credit' => 'Osamah / Pexels',
                'subjects' => ['tourism'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-khezez-23414762.jpg',
                'alt' => 'حديقة على الواجهة البحرية عند الغروب تتقدّمها ممرات مشاة ومسطحات خضراء.',
                'credit' => 'Khezez / Pexels',
                'subjects' => ['tourism'],
                'orientation' => 'portrait',
            ],
            [
                'file' => 'pexels-afatihdagli-30137366.jpg',
                'alt' => 'مخيّم صحراوي بسيارتَي دفع رباعي وخيمة سطح عند سفح جرف صخري أحمر.',
                'credit' => 'A. Fatih Dagli / Pexels',
                'subjects' => ['tourism', 'regions'],
                'orientation' => 'portrait',
            ],

            // ---- Heritage and regions -----------------------------------
            [
                'file' => 'pexels-abdullah-alallah-314142096-28558761.jpg',
                'alt' => 'بيوت جدة التاريخية بواجهاتها الحجرية ورواشينها الخشبية البارزة في ضوء الغروب.',
                'credit' => 'Abdullah Alallah / Pexels',
                'subjects' => ['heritage'],
                'orientation' => 'portrait',
            ],
            [
                'file' => 'pexels-moph-36230496.jpg',
                'alt' => 'رواشين خشبية بلونيها الأزرق والبنّي على واجهات مبانٍ تراثية في جدة التاريخية.',
                'credit' => 'Moph / Pexels',
                'subjects' => ['heritage'],
                'orientation' => 'portrait',
            ],
            [
                'file' => 'pexels-abdullahg-11695874.jpg',
                'alt' => 'دلال قهوة فخّارية معروضة في سوق شعبي، معلّق على إحداها زينة من الخرز الملوّن.',
                'credit' => 'Abdullah G / Pexels',
                'subjects' => ['heritage'],
                'orientation' => 'portrait',
            ],
            [
                'file' => 'pexels-ajmal-ali-paleri-1559248236-36178314.jpg',
                'alt' => 'قطيع من الإبل يسير في سهل صحراوي تحيط به تلال رملية وصخور بركانية داكنة.',
                'credit' => 'Ajmal Ali Paleri / Pexels',
                'subjects' => ['regions'],
                'orientation' => 'landscape',
            ],
            [
                'file' => 'pexels-hossamashoor-29465254.jpg',
                'alt' => 'مسجد بمئذنتين توأمين على واجهة مائية عند الغروب، وترسو أمامه عبّارة ركاب.',
                'credit' => 'Hossam Ashoor / Pexels',
                'subjects' => ['heritage'],
                'orientation' => 'portrait',
            ],
        ];
    }

    /**
     * Which image subjects suit which editorial category. Used to keep a wind
     * farm off a heritage story; it never overrides the harder rule, which is
     * that no image repeats inside one homepage section.
     *
     * @return array<string, array<int, string>>
     */
    public static function affinity(): array
    {
        return [
            'saudi' => ['riyadh', 'energy', 'regions', 'tourism', 'heritage'],
            'business' => ['business', 'markets', 'industry', 'realestate', 'riyadh'],
            'insights' => ['markets', 'business', 'energy', 'industry'],
            'startups' => ['business', 'riyadh', 'realestate', 'markets'],
            'global' => ['industry', 'energy', 'markets', 'regions'],
            'stories' => ['heritage', 'tourism', 'business', 'regions'],
        ];
    }
}
