<?php

return [
    'attachments' => [
        'disk' => env('SUPPORT_TICKET_ATTACHMENT_DISK', 'local'),
        'directory' => 'support-tickets',
        'max_files' => 5,
        'max_kilobytes' => (int) env('SUPPORT_TICKET_ATTACHMENT_MAX_KB', 10240),
    ],

    'rate_limits' => [
        'create_per_minute' => (int) env('SUPPORT_TICKET_CREATE_RATE_PER_MINUTE', 5),
        'reply_per_minute' => (int) env('SUPPORT_TICKET_REPLY_RATE_PER_MINUTE', 20),
    ],
];
