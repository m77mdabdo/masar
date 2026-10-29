<?php

return [
  'management_schema' => [
    'actions' => [
      'label' => 'ऑथेंटिकेटर ऐप',
      'below_content' => 'लॉगिन सत्यापन के लिए अस्थायी कोड बनाने हेतु एक सुरक्षित ऐप का उपयोग करें।',
      'messages' => [
        'enabled' => 'सक्षम',
        'disabled' => 'अक्षम',
      ],
    ],
  ],
  'login_form' => [
    'label' => 'अपने ऑथेंटिकेटर ऐप के कोड का उपयोग करें',
    'code' => [
      'label' => 'ऑथेंटिकेटर ऐप से 6 अंकों का कोड दर्ज करें',
      'validation_attribute' => 'कोड',
      'actions' => [
        'use_recovery_code' => [
          'label' => 'इसके बजाय रिकवरी कोड का उपयोग करें',
        ],
      ],
      'messages' => [
        'invalid' => 'आपके द्वारा दर्ज किया गया कोड अमान्य है।',
      ],
    ],
    'recovery_code' => [
      'label' => 'या, एक रिकवरी कोड दर्ज करें',
      'validation_attribute' => 'रिकवरी कोड',
      'messages' => [
        'invalid' => 'आपके द्वारा दर्ज किया गया रिकवरी कोड अमान्य है।',
      ],
    ],
  ],
];
