<?php

return [
  'actions' => [
    'register' => [
      'before' => 'या',
      'label' => 'खाते के लिए साइन अप करें',
    ],
    'request_password_reset' => [
      'label' => 'पासवर्ड भूल गए?',
    ],
  ],
  'multi_factor' => [
    'heading' => 'अपनी पहचान सत्यापित करें',
    'subheading' => 'साइन इन जारी रखने के लिए आपको अपनी पहचान सत्यापित करनी होगी।',
    'form' => [
      'provider' => [
        'label' => 'आप कैसे सत्यापित करना चाहेंगे?',
      ],
      'actions' => [
        'authenticate' => [
          'label' => 'साइन इन की पुष्टि करें',
        ],
      ],
    ],
  ],
  'notifications' => [
    'throttled' => [
      'body' => 'कृपया :seconds सेकंड में फिर से प्रयास करें।',
    ],
  ],
];
