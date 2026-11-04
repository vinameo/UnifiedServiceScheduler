<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Channel Drivers (D-11.3)
    |--------------------------------------------------------------------------
    |
    | Supported drivers:
    |   - SMS: "log", "failing"
    |   - EMAIL: "mail", "log", "failing"
    |   - DMS: "log", "failing", "webhook"
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
