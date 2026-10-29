<?php

return [
  'single' => [
    'label' => 'स्थायी रूप से हटाएँ',
    'modal' => [
      'heading' => ':label स्थायी रूप से हटाएँ',
      'actions' => [
        'delete' => [
          'label' => 'हटाएँ',
        ],
      ],
    ],
    'notifications' => [
      'deleted' => [
        'title' => 'हटा दिया',
      ],
    ],
  ],
  'multiple' => [
    'label' => 'चयनित स्थायी रूप से हटाएँ',
    'modal' => [
      'heading' => 'चयनित :label स्थायी रूप से हटाएँ',
      'actions' => [
        'delete' => [
          'label' => 'हटाएँ',
        ],
      ],
    ],
    'notifications' => [
      'deleted' => [
        'title' => 'हटा दिया',
      ],
      'deleted_partial' => [
        'title' => ':total में से :count हटाए गए',
        'missing_authorization_failure_message' => 'आपको :count हटाने की अनुमति नहीं है।',
        'missing_processing_failure_message' => ':count हटाए नहीं जा सके।',
      ],
      'deleted_none' => [
        'title' => 'हटाने में विफल',
        'missing_authorization_failure_message' => 'आपको :count हटाने की अनुमति नहीं है।',
        'missing_processing_failure_message' => ':count हटाए नहीं जा सके।',
      ],
    ],
  ],
];
