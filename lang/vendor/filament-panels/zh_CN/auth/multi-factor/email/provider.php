<?php

return [
  'management_schema' => [
    'actions' => [
      'label' => '邮件验证码',
      'below_content' => '登录时通过邮箱接收临时验证码以验证您的身份。',
      'messages' => [
        'enabled' => '已启用',
        'disabled' => '已停用',
      ],
    ],
  ],
  'login_form' => [
    'label' => '向您的邮箱发送验证码',
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
      ],
    ],
  ],
];
