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
        'api_keys' => array_values(array_filter([
            env('CALENDLY_API_KEY'),
            env('CALENDLY_API_KEY_2'),
            env('CALENDLY_API_KEY_3'),
            env('CALENDLY_API_KEY_4'),
        ])),
        'user_uri' => env('CALENDLY_USER_URI'),
        'default_event_type' => env('CALENDLY_DEFAULT_EVENT_TYPE', 'https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb'),
        't10_event_type' => env('CALENDLY_T10_EVENT_TYPE', 'https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb'),
        't0_event_type' => env('CALENDLY_T0_EVENT_TYPE', 'https://api.calendly.com/event_types/ff20712e-6387-4965-9026-dee4c7e5ef62'),
        'live_call_event_type' => env('CALENDLY_LIVE_CALL_EVENT_TYPE'),
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

    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],

    'webhooks' => [
        'lead_webhook_url' => env('LEAD_WEBHOOK_URL'),
    ],

    'referral' => [
        'default_reward_amount' => env('REFERRAL_DEFAULT_REWARD_AMOUNT', 14.00),
        'webhook_url' => env('REFERRAL_WEBHOOK_URL'),
    ],
];
