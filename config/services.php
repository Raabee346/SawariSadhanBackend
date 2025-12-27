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

    'khalti' => [
        'secret_key' => env('KHALTI_SECRET_KEY', 'test_secret_key_xxxxxxxxxxxxxxxxx'),
        'public_key' => env('KHALTI_PUBLIC_KEY', 'test_public_key_xxxxxxxxxxxxxxxxx'),
        'sandbox' => env('KHALTI_SANDBOX', true), // Set to false for production
        'return_url' => env('KHALTI_RETURN_URL', ''),
        'website_url' => env('KHALTI_WEBSITE_URL', ''),
    ],

];
