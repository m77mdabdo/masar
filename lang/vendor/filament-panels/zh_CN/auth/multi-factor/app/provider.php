<?php

return [
  'management_schema' => [
    'actions' => [
      'label' => '身份验证器应用',
      'below_content' => '使用安全的应用生成用于登录验证的临时验证码。',
      'messages' => [
        'enabled' => '已启用',
        'disabled' => '已停用',
      ],
    ],
  ],
  'login_form' => [
    'label' => '使用身份验证器应用中的验证码',
    'code' => [
      'label' => '输入身份验证器应用中的 6 位验证码',
      'validation_attribute' => '验证码',
      'actions' => [
        'use_recovery_code' => [
          'label' => '改用恢复码',
        ],
      ],
      'messages' => [
        'invalid' => '您输入的验证码无效。',
      ],
    ],
    'recovery_code' => [
      'label' => '或者，输入恢复码',
      'validation_attribute' => '恢复码',
      'messages' => [
        'invalid' => '您输入的恢复码无效。',
      ],
    ],
  ],
];
