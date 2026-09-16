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

    /*
     * Stripe.
     *
     * `key` / `secret` / `client_id` drive Stripe Connect (referrer payouts). The keys below
     * them drive the embedded card checkout ported from rl-elementor-blocks, which keeps a
     * live/test key pair and a runtime toggle instead of a single secret — see
     * docs/stripe-payments.md for the WP option each one replaces.
     *
     * Live mode reuses STRIPE_KEY / STRIPE_SECRET rather than introducing a parallel
     * STRIPE_LIVE_* pair. STRIPE_TEST_KEY / STRIPE_TEST_SECRET already exist in .env and were
     * previously dead (see docs/configuration.md); the checkout is what now reads them.
     */
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'client_id' => env('STRIPE_CONNECT_CLIENT_ID'),

        /*
         * Referrer payouts (Stripe Connect) are shelved until further notice (2026-09-16).
         * Off means StripeConnectGateway makes no outbound call at all — no account creation,
         * no onboarding link, no transfer. Set STRIPE_CONNECT_ENABLED=true to bring it back.
         */
        'connect_enabled' => filter_var(env('STRIPE_CONNECT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

        'test_mode' => filter_var(env('STRIPE_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN),
        'live_publishable_key' => env('STRIPE_KEY'),
        'live_secret_key' => env('STRIPE_SECRET'),
        'test_publishable_key' => env('STRIPE_TEST_KEY'),
        'test_secret_key' => env('STRIPE_TEST_SECRET'),
        'webhook_forward_url' => env('STRIPE_WEBHOOK_FORWARD_URL'),
        'default_thankyou_url' => env('STRIPE_DEFAULT_THANKYOU_URL'),
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
        // Track API v1 — server side only, used by CustomerIOClient.
        'site_id' => env('CUSTOMERIO_SITE_ID'),
        'api_key' => env('CUSTOMERIO_API_KEY'),

        // CDP (Data Pipelines) source write key — browser side only, used by the
        // `window.cioanalytics` snippet TrackingHooks injects. A different product and a
        // different credential from site_id above; they are not interchangeable.
        'cdp_write_key' => env('CUSTOMERIO_CDP_WRITE_KEY'),
    ],

    'posthog' => [
        'api_key' => env('POSTHOG_API_KEY'),
        'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),
    ],

    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],

    /*
     * Stamped on every Lead and mapped to HubSpot's `source` property. The legacy Gravity Form
     * sent the constant 'Salvatori Forms'; v2 identifies itself instead, so the portal can tell
     * which system a contact came through during and after the cutover.
     */
    'lead' => [
        'data_source' => env('LEAD_DATA_SOURCE', 'Remote Leverage v2'),
    ],

    'webhooks' => [
        'lead_webhook_url' => env('LEAD_WEBHOOK_URL'),
    ],

    'referral' => [
        'default_reward_amount' => env('REFERRAL_DEFAULT_REWARD_AMOUNT', 14.00),
        'webhook_url' => env('REFERRAL_WEBHOOK_URL'),
    ],
];
