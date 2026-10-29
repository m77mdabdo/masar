<?php

return [
  'title' => 'अपना पासवर्ड रीसेट करें',
  'heading' => 'अपना पासवर्ड रीसेट करें',
  'form' => [
    'email' => [
      'label' => 'ईमेल पता',
    ],
    'password' => [
      'label' => 'पासवर्ड',
      'validation_attribute' => 'पासवर्ड',
    ],
    'password_confirmation' => [
      'label' => 'पासवर्ड की पुष्टि करें',
    ],
    'actions' => [
      'reset' => [
        'label' => 'पासवर्ड रीसेट करें',
      ],
    ],
  ],
  'notifications' => [
    'throttled' => [
      'title' => 'रीसेट के बहुत अधिक प्रयास',
      'body' => 'कृपया :seconds सेकंड में फिर से प्रयास करें।',
    ],
  ],
];
