<?php

return [
  'label' => '关闭',
  'modal' => [
    'heading' => '停用身份验证器应用',
    'description' => '确定要停止使用身份验证器应用吗？停用后，您的账户将失去一层额外的安全保护。',
    'form' => [
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
          'rate_limited' => '尝试次数过多，请稍后再试。',
        ],
      ],
      'recovery_code' => [
        'label' => '或者，输入恢复码',
        'validation_attribute' => '恢复码',
        'messages' => [
          'invalid' => '您输入的恢复码无效。',
          'rate_limited' => '尝试次数过多，请稍后再试。',
        ],
      ],
    ],
    'actions' => [
      'submit' => [
        'label' => '停用身份验证器应用',
      ],
    ],
  ],
  'notifications' => [
    'disabled' => [
      'title' => '身份验证器应用已停用',
    ],
  ],
];
