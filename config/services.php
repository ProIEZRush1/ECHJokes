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

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'api_ninjas' => [
        // Fallback to the provided key so jokes keep working even before
        // API_NINJAS_KEY is added to the Coolify env. Rotate by setting
        // API_NINJAS_KEY in env to override.
        'key' => env('API_NINJAS_KEY', 'GFq73fwrJKqgNPElV6xmuJhvPKIqMENgz5Wt3AcE'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'deepgram' => [
        'api_key' => env('DEEPGRAM_API_KEY'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_id' => env('STRIPE_PRICE_ID'),
    ],

];
