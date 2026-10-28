<?php

declare(strict_types=1);

return [
    '403' => [
        'body' => 'If you think this is a mistake, sign in with the right account or go back to the front page.',
        'heading' => 'You do not have access to this page',
    ],
    '404' => [
        'body' => 'The story may have moved, its link may have changed, or the address was typed differently. Search for what you meant, or start from a section.',
        'heading' => 'We could not find this page',
    ],
    '419' => [
        'body' => 'Too much time passed since the page was opened, so the form could not be sent. Go back, reload it and try again.',
        'heading' => 'This page has expired',
    ],
    '429' => [
        'body' => 'We received a lot of requests from this connection in a short time. Wait a minute and try again.',
        'heading' => 'Too many requests',
    ],
    '500' => [
        'body' => 'This page could not be shown because of a fault on the site, not your device or your connection. Try again shortly.',
        'heading' => 'Something went wrong on our side',
    ],
    '503' => [
        'body' => 'We are carrying out maintenance on the site. Try again shortly.',
        'heading' => 'MASAR is down for maintenance',
    ],
    'ui' => [
        'home' => 'Front page',
        'kicker' => 'Error',
        'retry' => 'Try again',
        'search' => 'Search MASAR…',
        'search_button' => 'Search',
        'search_label' => 'Search term',
        'sections' => 'Sections',
    ],
];
