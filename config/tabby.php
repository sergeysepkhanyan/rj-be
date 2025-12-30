<?php

return [
    'base_url' => env('TABBY_BASE_URL', 'https://api.tabby.ai'),
    'secret_key' => env('TABBY_SECRET_KEY'),
    'merchant_code' => env('TABBY_MERCHANT_CODE'),

    'urls' => [
        'success' => env('TABBY_SUCCESS_URL'),
        'cancel'  => env('TABBY_CANCEL_URL'),
        'failure' => env('TABBY_FAILURE_URL'),
    ],

    'webhook' => [
        'header_name'  => env('TABBY_WEBHOOK_HEADER_NAME', 'X-Tabby-Signature'),
        'header_value' => env('TABBY_WEBHOOK_HEADER_VALUE'),
    ],
];

