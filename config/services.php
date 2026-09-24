<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from_name' => env('MAIL_FROM_NAME', 'Direct Impact Development Network'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'info@directimpactnetwork.org'),
        'contact_from_address' => env('CONTACT_FROM_ADDRESS', 'contact@directimpactnetwork.org'),
        'contact_notification_email' => env('CONTACT_NOTIFICATION_EMAIL'),
        'public_website_url' => env('PUBLIC_WEBSITE_URL', 'https://www.directimpactnetwork.org'),
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

];
