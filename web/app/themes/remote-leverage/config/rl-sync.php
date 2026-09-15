<?php

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

    'options' => [
        CalendlyTokenPool::OPTION_KEY,
        CalendlyEventTypeRoleResolver::OPTION_KEY,
        CalendlyEventTypeDiscoveryService::BACKUP_OPTION_KEY,
        'rl_lead_webhook_url',
        'rl_slack_webhook_url',
        'rl_jlc_slack_webhook_url',
        'rl_custom_webhook_url',
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
