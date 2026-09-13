<?php

return [
    'default_trial_plan_slug' => env('DEFAULT_TRIAL_PLAN_SLUG', 'free-trial'),

    'checkout' => [
        'enabled' => true,
        'route' => 'billing.payment.create',
    ],

    'bank_transfer' => [
        'enabled' => (bool) env('BANK_TRANSFER_ENABLED', true),
        'bank_name' => env('BANK_TRANSFER_BANK_NAME'),
        'account_name' => env('BANK_TRANSFER_ACCOUNT_NAME'),
        'account_number' => env('BANK_TRANSFER_ACCOUNT_NUMBER'),
        'branch_name' => env('BANK_TRANSFER_BRANCH'),
        'routing_number' => env('BANK_TRANSFER_ROUTING_NUMBER'),
        'swift_code' => env('BANK_TRANSFER_SWIFT_CODE'),
        'iban' => env('BANK_TRANSFER_IBAN'),
        'currency' => env('BANK_TRANSFER_CURRENCY'),
        'instructions' => env('BANK_TRANSFER_INSTRUCTIONS'),
        'future_transfer_tolerance_minutes' => (int) env('BANK_TRANSFER_FUTURE_TOLERANCE_MINUTES', 15),
        'submission_rate_per_minute' => (int) env('BANK_TRANSFER_SUBMISSION_RATE_PER_MINUTE', 3),
        'proofs' => [
            'disk' => env('BANK_TRANSFER_PROOF_DISK', 'local'),
            'directory' => 'payment-proofs',
            'max_kilobytes' => (int) env('BANK_TRANSFER_PROOF_MAX_KB', 10240),
        ],
    ],
];
