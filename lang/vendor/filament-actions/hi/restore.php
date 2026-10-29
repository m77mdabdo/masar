<?php

return [
  'single' => [
    'label' => 'पुनर्स्थापित करें',
    'modal' => [
      'heading' => ':label पुनर्स्थापित करें',
      'actions' => [
        'restore' => [
          'label' => 'पुनर्स्थापित करें',
        ],
      ],
    ],
    'notifications' => [
      'restored' => [
        'title' => 'पुनर्स्थापित किया गया',
      ],
    ],
  ],
  'multiple' => [
    'label' => 'चयनित पुनर्स्थापित करें',
    'modal' => [
      'heading' => 'चयनित :label पुनर्स्थापित करें',
      'actions' => [
        'restore' => [
          'label' => 'पुनर्स्थापित करें',
        ],
      ],
    ],
    'notifications' => [
      'restored' => [
        'title' => 'पुनर्स्थापित किया गया',
      ],
      'restored_partial' => [
        'title' => ':total में से :count पुनर्स्थापित किए गए',
        'missing_authorization_failure_message' => 'आपको :count पुनर्स्थापित करने की अनुमति नहीं है।',
        'missing_processing_failure_message' => ':count पुनर्स्थापित नहीं किए जा सके।',
      ],
      'restored_none' => [
        'title' => 'पुनर्स्थापित करने में विफल',
        'missing_authorization_failure_message' => 'आपको :count पुनर्स्थापित करने की अनुमति नहीं है।',
        'missing_processing_failure_message' => ':count पुनर्स्थापित नहीं किए जा सके।',
      ],
    ],
  ],
];
