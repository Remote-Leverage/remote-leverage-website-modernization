<?php

use App\Ai\Abilities\ClonePageAbility;
use App\Ai\Abilities\CreateLandingPageAbility;
use App\Ai\Abilities\DescribePageAbility;
use App\Ai\Abilities\ErrorLogAbility;
use App\Ai\Abilities\LeadStatsAbility;
use App\Ai\Abilities\ListPagesAbility;
use App\Ai\Abilities\ListPatternsAbility;
use App\Ai\Abilities\QueryLeadsAbility;
use App\Ai\Abilities\UpdateLandingPageContentAbility;
use App\Ai\Abilities\UpdatePageSectionsAbility;
use App\Ai\Abilities\UploadMediaAbility;
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
    | These default to true as of 2026-09-18, by explicit decision: the marketing
    | team's whole workflow — edit a live page, publish a campaign, look at
    | enquiries, upload art — needs all four, and carrying them as per-
    | environment secrets meant a forgotten secret silently produced an agent
    | that could only draft, with no error anywhere to explain why.
    |
    | The trade is real and worth stating: an environment that sets nothing now
    | gets an agent that can publish, change live pages, read customer contact
    | details and write to the uploads volume. Closing one is still a per-
    | environment act — set the variable to false and the next deploy revokes
    | the capability, because ensure() reconciles in both directions.
    |
    */

    'content_agent' => [
        'login' => env('AI_AGENT_LOGIN', 'ai-content-agent'),
        'email' => env('AI_AGENT_EMAIL', 'ai-content-agent@remoteleverage.com'),
        'role' => env('AI_AGENT_ROLE', 'editor'),

        // Let clone-page publish directly instead of always leaving a draft.
        'can_publish' => env('AI_AGENT_CAN_PUBLISH', true),

        // Let update-page-sections change a page that is already live.
        'can_edit_published' => env('AI_AGENT_CAN_EDIT_PUBLISHED', true),

        // Let query-leads return real customer contact details.
        'can_read_leads' => env('AI_AGENT_CAN_READ_LEADS', true),

        // Let upload-media add files to the media library. Off by default
        // because an upload writes to the shared uploads volume and there is
        // no undo — the file stays until somebody deletes it by hand.
        'can_upload_media' => env('AI_AGENT_CAN_UPLOAD_MEDIA', true),

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

        // The other half of image editing: page fields address art by
        // attachment id, so without this one a session can rearrange existing
        // images but never introduce a new one.
        UploadMediaAbility::class,

        // Read-only enquiry data, gated on InsightsCapability rather than
        // edit_pages — query-leads returns customer PII, so a content agent
        // does not get it by default. Grant with `wp acorn rl:ai:grant-insights`.
        LeadStatsAbility::class,
        QueryLeadsAbility::class,

        // Reading a remote environment's application log, because nobody on this team has a
        // shell on one and every failure path here logs and carries on. REST-only, invoked by
        // `wp acorn rl:logs`, gated on the sync capability the credential already holds.
        ErrorLogAbility::class,

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
