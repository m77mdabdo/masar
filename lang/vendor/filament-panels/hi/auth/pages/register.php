<?php

return [
  'title' => 'पंजीकरण करें',
  'heading' => 'साइन अप करें',
  'actions' => [
    'login' => [
      'before' => 'या',
      'label' => 'अपने खाते में साइन इन करें',
    ],
  ],
  'form' => [
    'email' => [
      'label' => 'ईमेल पता',
    ],
    'name' => [
      'label' => 'नाम',
    ],
    'password' => [
      'label' => 'पासवर्ड',
      'validation_attribute' => 'पासवर्ड',
    ],
    'password_confirmation' => [
      'label' => 'पासवर्ड की पुष्टि करें',
    ],
    'actions' => [
      'register' => [
        'label' => 'साइन अप करें',
      ],
    ],
  ],
  'notifications' => [
    'throttled' => [
      'title' => 'पंजीकरण के बहुत अधिक प्रयास',
      'body' => 'कृपया :seconds सेकंड में फिर से प्रयास करें।',
    ],
  ],
];
