<?php

return [
    'profile' => [
        'avatar_disk' => env('ADMIN_PROFILE_AVATAR_DISK', 'public'),
        'avatar_directory' => 'admin/profile',
        'avatar_max_kilobytes' => (int) env('ADMIN_PROFILE_AVATAR_MAX_KB', 2048),
    ],
];
