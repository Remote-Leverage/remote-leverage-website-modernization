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

    'customer_io' => [
        // Track API v1 — server side only, used by CustomerIOClient.
        'site_id' => env('CUSTOMERIO_SITE_ID'),
        'api_key' => env('CUSTOMERIO_API_KEY'),

        // CDP (Data Pipelines) source write key — browser side only, used by the
        // `window.cioanalytics` snippet TrackingHooks injects. A different product and a
        // different credential from site_id above; they are not interchangeable.
        /*
         * Publishable browser key — recovered from the `analytics.load("…")` argument in the
         * page source of both remoteleverage.com and rl-testing.test, where it is served to
         * every visitor. Defaulted here for the same reason as the Sentry DSN: it is not a
         * secret, and holding it in Secrets Manager only made it unreachable.
         */
        'cdp_write_key' => env('CUSTOMERIO_CDP_WRITE_KEY', 'ebb5281c53e9fca6b1a5'),

        /*
         * Same production-only gate as PostHog and the pixels. The write key is
         * defaulted, so without this every staging PSI run and every test booking
         * would ingest into the production CDP source. Verified 2026-09-18: staging
         * /hire-va-4/ emitted the snippet and PSI TBT went 0 ms → 710 ms.
         */
        'environments' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'CUSTOMERIO_ENVIRONMENTS',
                env('PIXEL_ENVIRONMENTS', env('GTM_ENVIRONMENTS', 'production')),
            )),
        ))),
    ],

    /*
     * Meta Conversions API — the server-side `Lead`.
     *
     * This is the *only* conversion signal Meta gets from this site. There is no client-side
     * fbq('track','Lead') and there never was: on the legacy stack the HandL UTM Grabber posted
     * a Lead to the Graph API on every Gravity Forms submit, and removing that plugin at cutover
     * took the whole channel with it. Ads Manager read 0 against ~12 real conversions until this
     * was added on 2026-09-18.
     *
     * Pixel ids are NOT duplicated here — the gateway reads `pixels.meta.pixel_ids`, so the
     * browser pixel and the server-side event can never drift apart.
     *
     * Plain `env()` with no default, deliberately: unset means off. A wrong default here would
     * post real visitor PII to somebody else's pixel.
     */
    'meta_capi' => [
        'access_token' => env('META_CAPI_ACCESS_TOKEN'),

        /*
         * Defaults to the version the legacy integration used, because that is the one proven to
         * work with this token and this pixel pair (verified against its send log 2026-09-18).
         * Newer is usually better with Graph, but "usually" is not a reason to change the one
         * variable we have evidence for. Bump it deliberately.
         */
        'api_version' => trim((string) env('META_CAPI_API_VERSION', '')) ?: 'v11.0',

        'enabled' => filter_var(env('META_CAPI_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        // Set while validating in Events Manager -> Test Events; blank in normal operation.
        'test_event_code' => trim((string) env('META_CAPI_TEST_EVENT_CODE', '')),

        /*
         * 8s/5s, matching PostHogClient rather than CustomerIOClient's 3s/2s. The send is
         * deferred with afterResponse(), so a tight timeout costs conversions on a cold TLS
         * handshake and buys the visitor nothing.
         */
        'timeout' => (int) env('META_CAPI_TIMEOUT', 8),
        'connect_timeout' => (int) env('META_CAPI_CONNECT_TIMEOUT', 5),
    ],

    'posthog' => [
        /*
         * The `phc_` project key, defaulted because it is publishable by design — it is emitted
         * into the page HTML for every visitor, and it was committed in `config/pixels.php` as
         * the container's id until PostHog moved into the theme on 2026-09-18. Leaving it to an
         * unset environment variable is why the theme's snippet was dormant on production while
         * PostHog quietly arrived from GTM instead.
         *
         * Not a credential: it can only write events. `POSTHOG_PERSONAL_API_KEY`-class secrets
         * are a different thing and do not belong here.
         *
         * `?:` rather than an `env()` default, because an env() default only applies when the
         * variable is *absent*. Production has it present and empty — which is why PostHog went
         * dark on 2026-09-18 the moment the GTM tag was deleted: the default was there, and an
         * empty string beat it. An empty value means "not configured", not "configured as
         * nothing".
         */
        'api_key' => trim((string) env('POSTHOG_API_KEY', '')) ?: 'phc_3PbasnDYndH8YVEky0ksHrB3SFwBZKmzkf5bl37o8u0',
        'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),

        /*
         * Which `wp_get_environment_type()` values load the browser snippet.
         *
         * Production only, and required rather than cosmetic now that the key above has a
         * default: without it every local page load and every staging smoke test would ingest
         * into the production project, polluting the funnels and filling replay with traffic
         * that was never a customer. Mirrors `pixels.environments`, so one switch still moves
         * the whole tracking surface together.
         *
         * This preserves behaviour rather than changing it: PostHog used to arrive from GTM,
         * and `GTM_ENVIRONMENTS` already gated the container to production.
         */
        'environments' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'POSTHOG_ENVIRONMENTS',
                env('PIXEL_ENVIRONMENTS', env('GTM_ENVIRONMENTS', 'production')),
            )),
        ))),

        /*
         * Numeric project id — the one in the dashboard URL, NOT the `phc_` key. Used only to
         * build session-replay links for the lead timeline; nothing authenticates with it.
         */
        'project_id' => env('POSTHOG_PROJECT_ID', '282594'),

        // Replay links live on the app host, not the ingestion host in `host` above.
        'app_host' => env('POSTHOG_APP_HOST', 'https://us.posthog.com'),
    ],

    /*
     * ZeroBounce mailbox verification. The admin-screen value wins; this is the fallback, and
     * the same precedence every other credential in this file follows.
     */
    /*
     * Portal id, used only to build links into the CRM. The access token lives in the Leads
     * settings screen (admin first, env fallback) like every other credential here.
     */
    'hubspot' => [
        /*
         * `?:` rather than env()'s default argument. `HUBSPOT_PORTAL_ID=` sets the value to an
         * empty string, which *overrides* a default instead of falling back to it — see
         * docs/configuration.md. That silently emptied this and the CRM link vanished from
         * every Slack alert with no error anywhere.
         */
        'portal_id' => env('HUBSPOT_PORTAL_ID') ?: '243484989',
    ],

    'zerobounce' => [
        'api_key' => env('ZEROBOUNCE_API_KEY'),
    ],

    'slack' => [
        /*
         * The site's own Slack app, "Remote Leverage Website" — renamed from "Remote Leverage
         * Leads Application" once it carried live calls, referrals and the action buttons as
         * well as leads. `webhook_url` is the fallback for an environment with no token, and it
         * cannot thread. See docs/slack-app.md.
         */
        'bot_token' => env('SLACK_BOT_TOKEN'),
        // #new-appts (C086BBKUXL5) — the channel the Gravity Forms lead feed posts to. Held as
        // an ID rather than a name because a rename silently breaks a name and not an ID.
        // NOT C09HXD9S76Z: that is #sales-meetings, which the legacy `rl_jlc_slack_channel`
        // option points at for live-call alerts — a different notification entirely.
        'channel' => env('SLACK_CHANNEL', 'C086BBKUXL5'),
        'webhook_url' => env('SLACK_WEBHOOK_URL'),

        /*
         * Slack's app signing secret, which verifies that an inbound interaction really came
         * from Slack. Doing double duty as the feature flag for the action buttons: without it
         * `SlackInteractionController` refuses every request, so rendering buttons that post to
         * it would only produce Slack's "not configured to handle interactive responses" notice.
         * See `HandleLeadEventsForSlack::interactionsEnabled()`.
         *
         * Basic Information -> App Credentials -> Signing Secret, on api.slack.com/apps.
         */
        'signing_secret' => env('SLACK_SIGNING_SECRET'),

        /*
         * The legacy Gravity Forms feed alerted on the **partial** submission only
         * (`submission_type is not Final`), so sales sees leads that never finish booking.
         * A second alert when a lead does book is a v2 addition, off by default.
         */
        /*
         * A booked call posts as a reply in the lead's existing Slack thread.
         *
         * On by default since 2026-09-17. It was off because the legacy Gravity Forms feed
         * never sent one, but that is a description of the old system rather than a reason:
         * the submission alert says someone filled in a form, and the booking is the part
         * sales actually acts on. Set SLACK_NOTIFY_ON_BOOKING=false to silence it.
         */
        'notify_on_booking' => filter_var(env('SLACK_NOTIFY_ON_BOOKING', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
     * Stamped on every Lead and mapped to HubSpot's `source` property. The legacy Gravity Form
     * sent the constant 'Salvatori Forms'; v2 identifies itself instead, so the portal can tell
     * which system a contact came through during and after the cutover.
     */
    'lead' => [
        'data_source' => env('LEAD_DATA_SOURCE', 'Remote Leverage v2'),
    ],

    /*
     * The outgoing lead webhook: `HandleLeadEventsForWebhook` POSTs here on the step-one
     * partial capture, on the completed (Final) submission, and again when the booking
     * confirms. It is the v2 replacement for the Gravity Forms feed that eight production
     * forms posted to, which is why the path still says `gravityforms-leads` — the n8n flow on
     * the other end is the same one, and renaming the endpoint would orphan it.
     *
     * Defaulted here rather than left to `.env`, the same treatment `config/live-transfer.php`
     * gives its endpoint. This is a destination, not a credential: with no default, an
     * environment that never set `LEAD_WEBHOOK_URL` posts nowhere at all, and a feed that
     * silently sends nothing is indistinguishable from n8n dropping every lead. An env value
     * still overrides it, and the wp-admin setting (`lead_webhook_url`, mirrored to
     * `rl_lead_webhook_url`) is the last resort for an environment that has neither.
     *
     * `LEAD_WEBHOOK_URL=` with nothing after it is not the same as the variable being absent:
     * phpdotenv hands back an empty string, which beats the default and falls through to the
     * setting. That is the off switch, and it is why `.env.example` leaves the key commented.
     */
    'webhooks' => [
        'lead_webhook_url' => (string) env(
            'LEAD_WEBHOOK_URL',
            'https://n8n.srv1338052.hstgr.cloud/webhook/gravityforms-leads',
        ),
    ],

    'referral' => [
        'default_reward_amount' => env('REFERRAL_DEFAULT_REWARD_AMOUNT', 14.00),
        'webhook_url' => env('REFERRAL_WEBHOOK_URL'),
    ],
];
