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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'ip_geolocation' => [
        'enabled' => (bool) env('IP_GEOLOCATION_ENABLED', true),
        'url' => env('IP_GEOLOCATION_URL', 'https://ipwho.is/{ip}'),
        'connect_timeout' => (float) env('IP_GEOLOCATION_CONNECT_TIMEOUT', 0.5),
        'timeout' => (float) env('IP_GEOLOCATION_TIMEOUT', 1.5),
        'cache_ttl' => (int) env('IP_GEOLOCATION_CACHE_TTL', 86400),
    ],

    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_ENABLED', false),
        'api_version' => env('WHATSAPP_CLOUD_API_VERSION', 'v23.0'),
        'base_url' => env('WHATSAPP_CLOUD_API_BASE_URL', 'https://graph.facebook.com'),
        'app_id' => env('WHATSAPP_APP_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'system_user_access_token' => env('WHATSAPP_SYSTEM_USER_ACCESS_TOKEN'),
        'business_portfolio_id' => env('WHATSAPP_BUSINESS_PORTFOLIO_ID'),
        'embedded_signup_config_id' => env('WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),
        'redirect_uri' => env('WHATSAPP_REDIRECT_URI'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'shared_waba_id' => env('WHATSAPP_SHARED_WABA_ID'),
        'shared_phone_number_id' => env('WHATSAPP_SHARED_PHONE_NUMBER_ID'),
        'shared_display_phone_number' => env('WHATSAPP_SHARED_DISPLAY_PHONE_NUMBER'),
        'shared_verified_name' => env('WHATSAPP_SHARED_VERIFIED_NAME'),
        'shared_access_token' => env('WHATSAPP_SHARED_ACCESS_TOKEN'),
        'request_timeout' => (int) env('WHATSAPP_REQUEST_TIMEOUT', 20),
        'allow_text_reminders' => (bool) env('WHATSAPP_ALLOW_TEXT_REMINDERS', false),
    ],

    'sms' => [
        'enabled' => (bool) env('SMS_ENABLED', false),
        'provider' => env('SMS_PROVIDER', 'twilio'),
        'api_base_url' => env('SMS_API_BASE_URL', 'https://api.twilio.com'),
        'request_timeout' => (int) env('SMS_REQUEST_TIMEOUT', 20),
        'webhook_url' => env('SMS_WEBHOOK_URL'),
        'webhook_auth_token' => env('SMS_WEBHOOK_AUTH_TOKEN'),
        'shared_account_sid' => env('SMS_SHARED_ACCOUNT_SID'),
        'shared_auth_token' => env('SMS_SHARED_AUTH_TOKEN'),
        'shared_from' => env('SMS_SHARED_FROM'),
        'shared_messaging_service_sid' => env('SMS_SHARED_MESSAGING_SERVICE_SID'),
    ],

];
