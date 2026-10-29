<?php

declare(strict_types=1);

return [
    'gate' => [
        'translation_unreviewed' => '该译文尚未审核。译文须经译者以外的人员审核通过后方可发布。',
    ],
    'language' => [
        'menu' => '后台语言',
    ],
    'translation' => [
        'action_retry' => '重新翻译',
        'action_review' => '审核通过译文',
        'action_translate' => '翻译为…',
        'exists' => '该文章已有:locale版本。',
        'file_changed' => '该文件自上次记录后已更改，请审核当前版本后重试。',
        'language' => '语言',
        'not_a_translation' => '该文章为原文，并非译文。',
        'not_retryable' => '当前状态下无法重新翻译。',
        'opened' => '已创建译文草稿。',
        'original' => '原文',
        'outdated' => '原文已更改',
        'protected' => '该译文已审核，不会被自动替换，请手动编辑。',
        'requeued' => '已重新加入翻译队列。',
        'review_confirm' => '您确认该译文与原文当前版本一致。此后它不会再被自动替换。',
        'reviewed' => '译文已审核通过。',
        'same_locale' => '该文章本就是用此语言撰写的。',
        'self_review' => '译者不能审核自己的译文。',
        'source_file' => '这是阿拉伯语源文件，不作为译文审核。',
        'status' => '翻译状态',
        'subheading_original' => '其他版本：:versions',
        'subheading_translation' => ':source 原文的:language译文——:state',
        'target' => '语言',
        'unknown_locale' => '未知语言：:locale',
    ],
    'upload' => [
        'dimensions' => '图片任一边不得超过 :max 像素。',
        'not_an_image' => '该文件不是有效的 JPEG、PNG 或 WebP 图片。',
        'too_large' => '文件超过 :kb KB。',
    ],
];
