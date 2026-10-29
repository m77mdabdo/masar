<?php

return [
  'form' => [
    'password' => [
      'validation_attribute' => '密码',
    ],
    'password_confirmation' => [
      'validation_attribute' => '确认密码',
    ],
  ],
  'multi_factor_authentication' => [
    'label' => '双重身份验证（2FA）',
  ],
  'notifications' => [
    'email_change_verification_sent' => [
      'title' => '已发送邮箱地址更改请求',
      'body' => '更改邮箱地址的请求已发送至 :email。请查收邮件以验证此更改。',
    ],
    'throttled' => [
      'title' => '请求次数过多，请在 :seconds 秒后重试。',
      'body' => '请在 :seconds 秒后重试。',
    ],
  ],
];
