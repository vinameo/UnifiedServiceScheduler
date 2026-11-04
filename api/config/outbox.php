<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Channel Drivers (D-11.3)
    |--------------------------------------------------------------------------
    |
    | Supported drivers:
    |   - SMS: "log", "failing", "table"
    |   - EMAIL: "mail", "log", "failing", "table"
    |   - DMS: "log", "failing", "webhook", "table"
    |
    */

    'sms' => [
        'driver' => env('OUTBOX_SMS_DRIVER', 'log'),
    ],

    'email' => [
        'driver' => env('OUTBOX_EMAIL_DRIVER', 'mail'),
    ],

    'dms' => [
        'driver' => env('OUTBOX_DMS_DRIVER', 'log'),
        'webhook_url' => env('DMS_WEBHOOK_URL'),
    ],
];
