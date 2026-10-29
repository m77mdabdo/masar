<?php

return [
  'management_schema' => [
    'actions' => [
      'label' => 'توثیق کنندہ ایپ',
      'below_content' => 'لاگ ان کی تصدیق کے لیے عارضی کوڈ بنانے کے لیے ایک محفوظ ایپ استعمال کریں۔',
      'messages' => [
        'enabled' => 'فعال',
        'disabled' => 'غیر فعال',
      ],
    ],
  ],
  'login_form' => [
    'label' => 'اپنی توثیق کنندہ ایپ کا کوڈ استعمال کریں',
    'code' => [
      'label' => 'توثیق کنندہ ایپ سے 6 ہندسوں کا کوڈ درج کریں',
      'validation_attribute' => 'کوڈ',
      'actions' => [
        'use_recovery_code' => [
          'label' => 'اس کے بجائے ریکوری کوڈ استعمال کریں',
        ],
      ],
      'messages' => [
        'invalid' => 'آپ کا درج کردہ کوڈ غلط ہے۔',
      ],
    ],
    'recovery_code' => [
      'label' => 'یا، ریکوری کوڈ درج کریں',
      'validation_attribute' => 'ریکوری کوڈ',
      'messages' => [
        'invalid' => 'آپ کا درج کردہ ریکوری کوڈ غلط ہے۔',
      ],
    ],
  ],
];
