<?php

return [
  'label' => 'सेट अप करें',
  'modal' => [
    'heading' => 'ऑथेंटिकेटर ऐप सेट अप करें',
    'description' => 'इस प्रक्रिया को पूरा करने के लिए आपको Google Authenticator (<x-filament::link href="https://itunes.apple.com/us/app/google-authenticator/id388497605" target="_blank">iOS</x-filament::link>, <x-filament::link href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">Android</x-filament::link>) जैसे ऐप की आवश्यकता होगी।',
    'content' => [
      'qr_code' => [
        'instruction' => 'इस QR कोड को अपने ऑथेंटिकेटर ऐप से स्कैन करें:',
        'alt' => 'ऑथेंटिकेटर ऐप से स्कैन करने के लिए QR कोड',
      ],
      'text_code' => [
        'instruction' => 'या यह कोड मैन्युअल रूप से दर्ज करें:',
        'messages' => [
          'copied' => 'कॉपी किया गया',
        ],
      ],
      'recovery_codes' => [
        'instruction' => 'कृपया निम्नलिखित रिकवरी कोड किसी सुरक्षित स्थान पर सहेजें। ये केवल एक बार दिखाए जाएँगे, लेकिन यदि आप अपने ऑथेंटिकेटर ऐप तक पहुँच खो देते हैं तो आपको इनकी आवश्यकता होगी:',
      ],
    ],
    'form' => [
      'code' => [
        'label' => 'ऑथेंटिकेटर ऐप से 6 अंकों का कोड दर्ज करें',
        'validation_attribute' => 'कोड',
        'below_content' => 'हर बार साइन इन करते समय या संवेदनशील क्रियाएँ करते समय आपको अपने ऑथेंटिकेटर ऐप से 6 अंकों का कोड दर्ज करना होगा।',
        'messages' => [
          'invalid' => 'आपके द्वारा दर्ज किया गया कोड अमान्य है।',
          'rate_limited' => 'बहुत अधिक प्रयास। कृपया बाद में फिर से प्रयास करें।',
        ],
      ],
    ],
    'actions' => [
      'submit' => [
        'label' => 'ऑथेंटिकेटर ऐप सक्षम करें',
      ],
    ],
  ],
  'notifications' => [
    'enabled' => [
      'title' => 'ऑथेंटिकेटर ऐप सक्षम कर दिया गया है',
    ],
  ],
];
