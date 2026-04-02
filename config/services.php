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

    'biblionet' => [
        'base_url'      => env('BIBLIONET_BASE_URL', 'https://biblionet.gr/wp-json/biblionetwebservice'),
        'client_id'     => env('BIBLIONET_CLIENT_ID'),
        'client_secret' => env('BIBLIONET_CLIENT_SECRET'),
        // Max requests per second. BIBLIONET terms require polite crawling.
        'rate_limit'    => (int) env('BIBLIONET_RATE_LIMIT', 1),
    ],

];
