<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Integration call recording
    |--------------------------------------------------------------------------
    |
    | Records the full request and response of every outbound call to a
    | third-party integration, so a failed booking or a rejected HubSpot sync
    | can be diagnosed from what was actually sent and actually returned
    | rather than from a one-line summary written before the fact.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Queue worker monitoring
    |--------------------------------------------------------------------------
    |
    | WR-106. The worker reports its own failures through the exception handler
    | already; what needs declaring is the monitor that notices when the worker
    | itself stops, because a process that is not running raises nothing.
    |
    */

    'queue' => [

        /*
         * Sentry Crons monitor slug for "a queue worker started".
         *
         * Empty switches the heartbeat off, which is the default and the right
         * default: `QUEUE_CONNECTION` is unset in every deployed environment,
         * so there is no worker to miss. Set this in the same change that sets
         * `QUEUE_CONNECTION` on an environment, never before — a monitor that
         * alerts on the absence of something deliberately switched off is a
         * monitor people learn to ignore.
         */
        'sentry_monitor' => (string) env('QUEUE_SENTRY_MONITOR', ''),
    ],

    'integration_calls' => [

        'enabled' => (bool) env('RL_RECORD_INTEGRATION_CALLS', true),

        /*
         * Hosts we care about, mapped to the name shown in the admin.
         *
         * Matched against the request host as a suffix, so `api.calendly.com`
         * and `calendly.com` both resolve to `calendly`.
         */
        'hosts' => [
            'calendly.com' => 'calendly',
            'hubapi.com' => 'hubspot',
            'hubspot.com' => 'hubspot',
            'slack.com' => 'slack',
            'stripe.com' => 'stripe',
            'googleapis.com' => 'google',
            'zerobounce.net' => 'zerobounce',
            'posthog.com' => 'posthog',
            'customer.io' => 'customerio',
            'customerioapi.com' => 'customerio',
            // Meta Conversions API (graph.facebook.com). The access_token travels in a JSON
            // body so `redact_keys` below fingerprints it; never move it to the query string.
            'facebook.com' => 'meta',
        ],

        /*
         * Whether to record calls to hosts not listed above.
         *
         * Off by default. The bulk content-import commands fetch hundreds of
         * pages of HTML in a single run, and recording those would bury the
         * integration traffic this table exists to make visible. A genuinely
         * new integration should earn a line in `hosts` rather than arriving
         * through a catch-all.
         */
        'record_unknown_hosts' => (bool) env('RL_RECORD_UNKNOWN_HOSTS', false),

        /*
         * Outgoing webhooks go through WordPress' HTTP API rather than the
         * Laravel client, so they are captured separately and always recorded:
         * the destination is operator-configured and cannot be allowlisted here.
         */
        'record_wp_http' => (bool) env('RL_RECORD_WP_HTTP', true),

        /*
         * Bodies are truncated at this many bytes. A response large enough to
         * exceed it is a page of HTML from an error proxy, not an API payload,
         * and the first 64KB is enough to identify it.
         */
        'max_body_bytes' => 65536,

        /*
         * Days to keep. These rows carry full lead PII by design — that is the
         * point of them — so they are pruned rather than kept indefinitely.
         */
        'retention_days' => (int) env('RL_INTEGRATION_CALL_RETENTION_DAYS', 30),

        /*
         * Header names whose value is replaced with a fingerprint.
         *
         * The fingerprint deliberately still identifies *which* credential was
         * used — see IntegrationCallRecorder::fingerprint(). Knowing Calendly
         * fell through to its second token is most of the diagnosis; knowing
         * the token itself only turns the activity log into a place credentials
         * leak from.
         */
        'redact_headers' => [
            'authorization',
            'proxy-authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'api-key',
            'apikey',
            'x-auth-token',
            'stripe-signature',
            'x-hub-signature',
            'x-hub-signature-256',
            'x-slack-signature',
            'calendly-webhook-signature',
        ],

        /*
         * JSON keys whose value is replaced with a fingerprint, at any depth.
         */
        'redact_keys' => [
            'token', 'access_token', 'refresh_token', 'id_token', 'bot_token',
            'secret', 'client_secret', 'signing_secret', 'webhook_secret',
            'password', 'api_key', 'apikey', 'private_key', 'authorization',
        ],
    ],

];
