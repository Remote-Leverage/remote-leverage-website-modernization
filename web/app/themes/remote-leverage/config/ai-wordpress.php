<?php

use App\Ai\Abilities\ClonePageAbility;
use App\Ai\Abilities\CreateLandingPageAbility;
use App\Ai\Abilities\DescribePageAbility;
use App\Ai\Abilities\LeadStatsAbility;
use App\Ai\Abilities\ListPagesAbility;
use App\Ai\Abilities\ListPatternsAbility;
use App\Ai\Abilities\QueryLeadsAbility;
use App\Ai\Abilities\UpdateLandingPageContentAbility;
use App\Ai\Abilities\UpdatePageSectionsAbility;
use App\Domains\Sync\Abilities\BeginTransferAbility;
use App\Domains\Sync\Abilities\CheckMediaFilesAbility;
use App\Domains\Sync\Abilities\ExportLandingPageAbility;
use App\Domains\Sync\Abilities\ExportMediaManifestAbility;
use App\Domains\Sync\Abilities\ExportSyncableSettingsAbility;
use App\Domains\Sync\Abilities\ExportTransferBatchAbility;
use App\Domains\Sync\Abilities\FinishTransferAbility;
use App\Domains\Sync\Abilities\ImportLandingPageAbility;
use App\Domains\Sync\Abilities\ImportSyncableSettingsAbility;
use App\Domains\Sync\Abilities\PurgeDatasetAbility;
use App\Domains\Sync\Abilities\PurgeTransferLogsAbility;
use App\Domains\Sync\Abilities\ReadMediaFileAbility;
use App\Domains\Sync\Abilities\ReceiveMediaFileAbility;
use App\Domains\Sync\Abilities\ReceiveTransferChunkAbility;
use App\Domains\Sync\Abilities\RollbackTransferAbility;

return [

    /*
    |--------------------------------------------------------------------------
    | Abilities
    |--------------------------------------------------------------------------
    |
    | Here you may register the ability classes that should be registered with
    | the WordPress Abilities API (requires WordPress 6.9+). Each class should
    | extend Roots\AcornAi\Abilities\Ability and will be resolved through
    | Laravel's service container, giving your abilities full dependency
    | injection support.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Content agent identity
    |--------------------------------------------------------------------------
    |
    | The WordPress user an MCP client authenticates as. `wp acorn rl:ai:agent`
    | reconciles the user against this config, and rl:deploy calls the same
    | code on every container start, so these flags are the live definition of
    | what a Claude session may do on this environment.
    |
    | Every flag defaults to false, so an environment that sets nothing gets an
    | agent that can draft a page and nothing else — no publishing, no touching
    | anything already live, no access to enquiry data. Widen deliberately, per
    | environment; production is the one where the defaults are worth keeping.
    |
    */

    'content_agent' => [
        'login' => env('AI_AGENT_LOGIN', 'ai-content-agent'),
        'email' => env('AI_AGENT_EMAIL', 'ai-content-agent@remoteleverage.com'),
        'role' => env('AI_AGENT_ROLE', 'editor'),

        // Let clone-page publish directly instead of always leaving a draft.
        'can_publish' => env('AI_AGENT_CAN_PUBLISH', false),

        // Let update-page-sections change a page that is already live.
        'can_edit_published' => env('AI_AGENT_CAN_EDIT_PUBLISHED', false),

        // Let query-leads return real customer contact details.
        'can_read_leads' => env('AI_AGENT_CAN_READ_LEADS', false),

        // Set false to stop rl:deploy reconciling the user on this environment.
        'provision_on_deploy' => env('AI_AGENT_PROVISION_ON_DEPLOY', true),
    ],

    'abilities' => [
        // Landing-page composition from patterns — exposed to MCP clients
        // (meta.mcp.public). Composing from whole patterns only; new copy
        // means a new patterns/*.php file and a deploy.
        ListPatternsAbility::class,
        CreateLandingPageAbility::class,
        UpdateLandingPageContentAbility::class,

        // Read and edit the pages that already exist, without a deploy. This
        // is the clone-a-landing-page path: list -> describe -> clone/update.
        ListPagesAbility::class,
        DescribePageAbility::class,
        ClonePageAbility::class,
        UpdatePageSectionsAbility::class,

        // Read-only enquiry data, gated on InsightsCapability rather than
        // edit_pages — query-leads returns customer PII, so a content agent
        // does not get it by default. Grant with `wp acorn rl:ai:grant-insights`.
        LeadStatsAbility::class,
        QueryLeadsAbility::class,

        // Local↔remote settings/page sync — REST-only, invoked by wp rl:sync:*.
        ExportSyncableSettingsAbility::class,
        ImportSyncableSettingsAbility::class,

        // Environment transfer — REST only, and each refuses to run in
        // production by its own WP_ENV regardless of caller (TransferAbility).
        BeginTransferAbility::class,
        ReceiveTransferChunkAbility::class,
        FinishTransferAbility::class,
        RollbackTransferAbility::class,
        CheckMediaFilesAbility::class,
        ReceiveMediaFileAbility::class,

        // Pull: the remote acts as the source. All three are read-only.
        ExportTransferBatchAbility::class,
        ExportMediaManifestAbility::class,
        ReadMediaFileAbility::class,

        // Maintenance. Never transfers anything; only empties purgeable datasets.
        PurgeDatasetAbility::class,
        PurgeTransferLogsAbility::class,
        ExportLandingPageAbility::class,
        ImportLandingPageAbility::class,
    ],

];
