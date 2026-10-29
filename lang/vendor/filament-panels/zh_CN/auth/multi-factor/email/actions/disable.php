<?php

return [
  'label' => '关闭',
  'modal' => [
    'heading' => '停用邮件验证码',
    'description' => '确定要停止接收邮件验证码吗？停用后，您的账户将失去一层额外的安全保护。',
    'form' => [
      'code' => [
        'label' => '输入我们通过邮件发送给您的 6 位验证码',
        'validation_attribute' => '验证码',
        'actions' => [
          'resend' => [
            'label' => '通过邮件发送新验证码',
            'notifications' => [
              'resent' => [
                'title' => '我们已通过邮件向您发送了新验证码',
              ],
              'throttled' => [
                'title' => '重新发送次数过多，请稍候再请求新验证码。',
              ],
            ],
          ],
        ],
        'messages' => [
          'invalid' => '您输入的验证码无效。',
          'rate_limited' => '尝试次数过多，请稍后再试。',
        ],
      ],
    ],
    'actions' => [
      'submit' => [
        'label' => '停用邮件验证码',
      ],
    ],
  ],
  'notifications' => [
    'disabled' => [
      'title' => '邮件验证码已停用',
    ],
  ],
];
