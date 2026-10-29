<?php

return [
  'label' => 'ترتیب دیں',
  'modal' => [
    'heading' => 'توثیق کنندہ ایپ ترتیب دیں',
    'description' => 'اس عمل کو مکمل کرنے کے لیے آپ کو Google Authenticator جیسی ایپ (<x-filament::link href="https://itunes.apple.com/us/app/google-authenticator/id388497605" target="_blank">iOS</x-filament::link>, <x-filament::link href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">Android</x-filament::link>) کی ضرورت ہوگی۔',
    'content' => [
      'qr_code' => [
        'instruction' => 'اپنی توثیق کنندہ ایپ سے یہ QR کوڈ اسکین کریں:',
        'alt' => 'توثیق کنندہ ایپ سے اسکین کرنے کے لیے QR کوڈ',
      ],
      'text_code' => [
        'instruction' => 'یا یہ کوڈ خود درج کریں:',
        'messages' => [
          'copied' => 'کاپی ہو گیا',
        ],
      ],
      'recovery_codes' => [
        'instruction' => 'براہ کرم درج ذیل ریکوری کوڈز کسی محفوظ جگہ پر رکھ لیں۔ یہ صرف ایک بار دکھائے جائیں گے، لیکن اگر آپ توثیق کنندہ ایپ تک رسائی کھو دیں تو آپ کو ان کی ضرورت ہوگی:',
      ],
    ],
    'form' => [
      'code' => [
        'label' => 'توثیق کنندہ ایپ سے 6 ہندسوں کا کوڈ درج کریں',
        'validation_attribute' => 'کوڈ',
        'below_content' => 'ہر بار سائن ان کرتے وقت یا حساس کارروائیاں کرتے وقت آپ کو اپنی توثیق کنندہ ایپ سے 6 ہندسوں کا کوڈ درج کرنا ہوگا۔',
        'messages' => [
          'invalid' => 'آپ کا درج کردہ کوڈ غلط ہے۔',
          'rate_limited' => 'بہت زیادہ کوششیں۔ براہ کرم بعد میں دوبارہ کوشش کریں۔',
        ],
      ],
    ],
    'actions' => [
      'submit' => [
        'label' => 'توثیق کنندہ ایپ فعال کریں',
      ],
    ],
  ],
  'notifications' => [
    'enabled' => [
      'title' => 'توثیق کنندہ ایپ فعال کر دی گئی ہے',
    ],
  ],
];
