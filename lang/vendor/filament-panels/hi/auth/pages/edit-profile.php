<?php

return [
  'label' => 'प्रोफ़ाइल',
  'form' => [
    'email' => [
      'label' => 'ईमेल पता',
    ],
    'name' => [
      'label' => 'नाम',
    ],
    'password' => [
      'label' => 'नया पासवर्ड',
      'validation_attribute' => 'पासवर्ड',
    ],
    'password_confirmation' => [
      'label' => 'नए पासवर्ड की पुष्टि करें',
      'validation_attribute' => 'पासवर्ड पुष्टि',
    ],
    'current_password' => [
      'label' => 'वर्तमान पासवर्ड',
      'below_content' => 'सुरक्षा के लिए, जारी रखने हेतु कृपया अपने पासवर्ड की पुष्टि करें।',
      'validation_attribute' => 'वर्तमान पासवर्ड',
    ],
    'actions' => [
      'save' => [
        'label' => 'परिवर्तन सहेजें',
      ],
    ],
  ],
  'multi_factor_authentication' => [
    'label' => 'टू-फ़ैक्टर ऑथेंटिकेशन (2FA)',
  ],
  'notifications' => [
    'email_change_verification_sent' => [
      'title' => 'ईमेल पता बदलने का अनुरोध भेजा गया',
      'body' => 'आपका ईमेल पता बदलने का अनुरोध :email पर भेज दिया गया है। परिवर्तन सत्यापित करने के लिए कृपया अपना ईमेल देखें।',
    ],
    'saved' => [
      'title' => 'सहेजा गया',
    ],
    'throttled' => [
      'title' => 'बहुत अधिक अनुरोध। कृपया :seconds सेकंड में फिर से प्रयास करें।',
      'body' => 'कृपया :seconds सेकंड में फिर से प्रयास करें।',
    ],
  ],
  'actions' => [
    'cancel' => [
      'label' => 'रद्द करें',
    ],
  ],
];
