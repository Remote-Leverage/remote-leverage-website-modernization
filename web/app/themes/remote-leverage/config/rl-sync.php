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
    */

    'environments' => [
        'staging' => [
            'url' => env('STAGING_SYNC_URL'),
            'user' => env('STAGING_SYNC_USER'),
            'app_password' => env('STAGING_SYNC_APP_PASSWORD'),
        ],
        'production' => [
            'url' => env('PRODUCTION_SYNC_URL'),
            'user' => env('PRODUCTION_SYNC_USER'),
            'app_password' => env('PRODUCTION_SYNC_APP_PASSWORD'),
        ],
    ],

];
