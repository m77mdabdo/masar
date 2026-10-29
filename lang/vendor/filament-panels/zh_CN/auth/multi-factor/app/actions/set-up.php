<?php

return [
  'label' => '设置',
  'modal' => [
    'heading' => '设置身份验证器应用',
    'description' => '您需要使用 Google Authenticator 等应用（<x-filament::link href="https://itunes.apple.com/us/app/google-authenticator/id388497605" target="_blank">iOS</x-filament::link>、<x-filament::link href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">Android</x-filament::link>）来完成此过程。',
    'content' => [
      'qr_code' => [
        'instruction' => '使用您的身份验证器应用扫描此二维码：',
        'alt' => '供身份验证器应用扫描的二维码',
      ],
      'text_code' => [
        'instruction' => '或者手动输入此代码：',
        'messages' => [
          'copied' => '已复制',
        ],
      ],
      'recovery_codes' => [
        'instruction' => '请将以下恢复码保存在安全的地方。它们只会显示一次，但如果您无法访问身份验证器应用，将需要使用它们：',
      ],
    ],
    'form' => [
      'code' => [
        'label' => '输入身份验证器应用中的 6 位验证码',
        'validation_attribute' => '验证码',
        'below_content' => '每次登录或执行敏感操作时，您都需要输入身份验证器应用中的 6 位验证码。',
        'messages' => [
          'invalid' => '您输入的验证码无效。',
          'rate_limited' => '尝试次数过多，请稍后再试。',
        ],
      ],
    ],
    'actions' => [
      'submit' => [
        'label' => '启用身份验证器应用',
      ],
    ],
  ],
  'notifications' => [
    'enabled' => [
      'title' => '身份验证器应用已启用',
    ],
  ],
];
