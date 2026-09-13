<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Media Library
|--------------------------------------------------------------------------
| Only the keys we actually decide on. Everything else stays on the package
| default so an upgrade can change it without us noticing a stale copy.
|
| `disk_name` is the one that matters: CLAUDE.md §2 locks media to object
| storage, so production sets MEDIA_DISK=r2 and the app server never holds a
| byte of it. Locally it resolves to the `media` disk under storage/app/public
| so `storage:link` can serve it without an R2 account.
*/

return [
    'disk_name' => env('MEDIA_DISK', 'media'),

    // Conversions are generated inline by the seeder and by the admin upload
    // path; there is no queue worker in local and a hero without a conversion
    // is a broken card, not a delayed one.
    'queue_conversions_by_default' => env('MEDIA_QUEUE_CONVERSIONS', false),

    'max_file_size' => 1024 * (int) env('MEDIA_MAX_UPLOAD_KB', 12288),
];
