<?php

declare(strict_types=1);

// Source language. Every key here must exist in all six editions
// (tests/Feature/I18n/TranslationParityTest.php).

return [
    'gate' => [
        'translation_unreviewed' => 'هذه ترجمة لم يراجعها أحد بعد. لا تُنشر ترجمة قبل أن يعتمدها مراجع غير المترجم.',
    ],
    'language' => [
        'menu' => 'لغة اللوحة',
    ],
    'translation' => [
        'action_retry' => 'إعادة الترجمة',
        'action_review' => 'اعتماد الترجمة',
        'action_translate' => 'ترجمة إلى…',
        'exists' => 'توجد نسخة :locale من هذه المادة بالفعل.',
        'file_changed' => 'تغيّر الملف منذ آخر مزامنة؛ راجِع النسخة الحالية ثم أعد المحاولة.',
        'language' => 'اللغة',
        'not_a_translation' => 'هذه المادة أصلية وليست ترجمة.',
        'not_retryable' => 'لا يمكن إعادة الترجمة وهي في هذه الحالة.',
        'opened' => 'فُتحت مسودة الترجمة.',
        'original' => 'أصلية',
        'outdated' => 'تغيّر الأصل',
        'protected' => 'رُوجعت هذه الترجمة؛ لا تُستبدل آليًا. عدّلها يدويًا.',
        'requeued' => 'أُعيدت الترجمة إلى قائمة الانتظار.',
        'review_confirm' => 'تؤكد أن هذه الترجمة تطابق المادة الأصلية كما هي الآن. لن تُستبدل آليًا بعد ذلك.',
        'reviewed' => 'اعتُمدت الترجمة.',
        'same_locale' => 'المادة مكتوبة أصلًا بهذه اللغة.',
        'self_review' => 'لا يراجع المترجم ترجمته بنفسه.',
        'source_file' => 'هذا ملف المصدر العربي، ولا يُراجَع بوصفه ترجمة.',
        'status' => 'حالة الترجمة',
        'subheading_original' => 'نسخ بلغات أخرى: :versions',
        'subheading_translation' => 'ترجمة :language عن :source — :state',
        'target' => 'اللغة',
        'unknown_locale' => 'لغة غير معروفة: :locale',
    ],
];
