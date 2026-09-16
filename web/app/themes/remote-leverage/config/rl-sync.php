<?php

use App\Domains\Lead\Services\LeadSettingsService;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Scheduling\Services\CalendlyEventTypeDiscoveryService;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Syncable wp_options
    |--------------------------------------------------------------------------
    |
    | The only option keys the AI sync abilities (App\Domains\Sync) are allowed
    | to read or write. Deliberately a whitelist, not a full wp_options dump —
    | this environment's core/plugin option rows must never be overwritten by
    | a sync. Add a key here only when it is genuinely environment-specific
    | data (tokens, webhook URLs) that has no other way to reach staging.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Per-request timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for a remote environment to answer one sync call. Guzzle's
    | 30s default fits a 25-row chunk but not the calls that do more in one
    | request — the first chunk of a dataset also runs that dataset's clean, and
    | a rollback replays a whole session. Capped in practice by the CDN's own
    | origin-response timeout, which answers with a 504 of its own if it gives
    | up first.
    |
    */

    'request_timeout' => (int) env('SYNC_REQUEST_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Transient-failure retries
    |--------------------------------------------------------------------------
    |
    | Total attempts per sync call before giving up. A media push is ~1,600
    | calls, so a single 503 from a container rolling over — or one dropped
    | connection — would otherwise discard the whole transfer. Only connection
    | failures and 5xx are retried; a 4xx is a real refusal and fails at once.
    |
    */

    'retries' => (int) env('SYNC_RETRIES', 4),

    'options' => [
        /*
         * Site structure. Not environment-specific data like the rest of this
         * list — these exist here because a target that does not share them is
         * not a mirror in the way that matters: without permalink_structure
         * every post answers on a different URL than local and production, and
         * without page_for_posts the blog index renders empty however many
         * posts were transferred. Both were true of staging until 2026-09-15.
         *
         * page_on_front and page_for_posts are post IDs, so they are only
         * meaningful alongside a content transfer that put those posts on the
         * target at the same IDs. Pushing settings alone, onto a target whose
         * content came from somewhere else, points the front page at whatever
         * now occupies that ID.
         */
        'permalink_structure',
        'show_on_front',
        'page_on_front',
        'page_for_posts',

        CalendlyTokenPool::OPTION_KEY,
        CalendlyEventTypeRoleResolver::OPTION_KEY,
        CalendlyEventTypeDiscoveryService::BACKUP_OPTION_KEY,
        'rl_lead_webhook_url',
        'rl_slack_webhook_url',
        'rl_jlc_slack_webhook_url',
        'rl_custom_webhook_url',

        /*
         * The Lead settings blob (LeadSettingsService::OPTION_KEY).
         *
         * One row carrying every admin-set integration value: the Slack bot token and channel,
         * the HubSpot access token and portal id, the ZeroBounce key, and the email
         * domain/address lists. It is here because those are exactly the "environment-specific
         * data that has no other way to reach staging" this whitelist exists for — ECS maps
         * Secrets Manager keys to environment variables one at a time in the task definition,
         * so a newly added credential cannot reach staging any other way today.
         *
         * **It carries live credentials.** That is a deliberate trade, not an oversight: the
         * transport is HTTPS with an application-password credential, and the alternative is
         * staging silently running without the integrations. Remove this key once the task
         * definition reads the whole secret (see docker/entrypoint.sh) and the environment
         * variables win on their own again.
         */
        LeadSettingsService::OPTION_KEY,
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote environments
    |--------------------------------------------------------------------------
    |
    | Credentials for the dedicated "sync-service" WordPress user on each
    | remote environment (see docs/ai-mcp-and-sync.md). Never the same
    | credentials used by the MCP content-agent user.
    |
    | "body_auth" additionally sends the credential as a request body field,
    | for a remote sitting behind a CDN that strips the Authorization header.
    | TEMPORARY, paired with web/app/mu-plugins/rl-sync-body-auth.php, and off
    | unless an environment opts in. Turn it off the day CloudFront forwards
    | the header. Never available for production.
    |
    */

    'environments' => [
        'staging' => [
            'url' => env('STAGING_SYNC_URL'),
            'user' => env('STAGING_SYNC_USER'),
            'app_password' => env('STAGING_SYNC_APP_PASSWORD'),
            'body_auth' => env('STAGING_SYNC_BODY_AUTH', false),
        ],
        'production' => [
            'url' => env('PRODUCTION_SYNC_URL'),
            'user' => env('PRODUCTION_SYNC_USER'),
            'app_password' => env('PRODUCTION_SYNC_APP_PASSWORD'),
            'body_auth' => false,
        ],
    ],

];
