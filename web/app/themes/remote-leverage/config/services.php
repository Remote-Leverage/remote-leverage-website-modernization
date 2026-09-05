<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third-Party Services Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials, API tokens, and webhooks for external integrations
    | utilized by the Remote Leverage domain services.
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'client_id' => env('STRIPE_CONNECT_CLIENT_ID'),
    ],

    'calendly' => [
        'api_key' => env('CALENDLY_API_KEY'),
        'user_uri' => env('CALENDLY_USER_URI'),
        'webhook_signing_key' => env('CALENDLY_WEBHOOK_SIGNING_KEY'),
    ],

    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_CALENDAR_REFRESH_TOKEN'),
        'calendar_id' => env('GOOGLE_CALENDAR_ID', 'primary'),
    ],

    'customer_io' => [
        'site_id' => env('CUSTOMERIO_SITE_ID'),
        'api_key' => env('CUSTOMERIO_API_KEY'),
        'app_api_key' => env('CUSTOMERIO_APP_API_KEY'),
    ],

    'posthog' => [
        'api_key' => env('POSTHOG_API_KEY'),
        'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),
    ],

    'notion' => [
        'api_key' => env('NOTION_API_KEY'),
        'database_id' => env('NOTION_PARTNERS_DATABASE_ID'),
    ],
];
