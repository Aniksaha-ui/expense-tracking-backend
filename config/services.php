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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'jwt' => [
        'secret' => env('JWT_SECRET', env('APP_KEY')),
        'ttl' => (int) env('JWT_TTL', 60 * 24),
        'issuer' => env('JWT_ISSUER', env('APP_URL', 'expense-tracker')),
    ],
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id'  => env('TELEGRAM_CHAT_ID'),
    ],

    'transaction_ocr' => [
        'driver' => env('OCR_DRIVER', 'google_vision'),
        'enabled' => env('OCR_ENABLED', true),
        'openai' => [
            'api_key' => env('OCR_OPENAI_API_KEY'),
            'model' => env('OCR_OPENAI_MODEL', 'gpt-4.1-mini'),
        ],
        'google_vision' => [
            'project_id' => env('GOOGLE_CLOUD_PROJECT'),
            'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        ],
    ],

    'financial_overview_ai' => [
        // Keep this separate from receipt OCR so reporting can use a dedicated project/key.
        'api_key' => env('OPENAI_REPORT_API_KEY', env('OCR_OPENAI_API_KEY')),
        'model' => env('OPENAI_REPORT_MODEL', 'gpt-4o-mini'),
    ],
    'serper' => ['api_key' => env('SERPER_API_KEY')],
    'offers' => ['latitude' => env('OFFERS_LATITUDE'), 'longitude' => env('OFFERS_LONGITUDE'), 'location' => env('OFFERS_SEARCH_LOCATION', 'Bangladesh')],

];
