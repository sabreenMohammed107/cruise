<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Live USD → EGP rate (App\Services\CurrencyService). ExchangeRate-API: without a key the free open endpoint
    // (open.er-api.com, updated daily, no sign-up); with a key from exchangerate-api.com its v6 API.
    'exchange_rate' => [
        'key' => env('EXCHANGE_RATE_API_KEY'),
        'timeout' => (int) env('EXCHANGE_RATE_TIMEOUT', 5),
        'cache_hours' => (int) env('EXCHANGE_RATE_CACHE_HOURS', 12),
        // After a failed fetch, wait this long before trying again (the fallback rate is used meanwhile)
        'retry_minutes' => 15,
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
