<?php

use App\Ai\Abilities\CreateLandingPageAbility;
use App\Ai\Abilities\ListPatternsAbility;
use App\Ai\Abilities\UpdateLandingPageContentAbility;
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

    'abilities' => [
        // Landing-page composition — exposed to MCP clients (meta.mcp.public).
        ListPatternsAbility::class,
        CreateLandingPageAbility::class,
        UpdateLandingPageContentAbility::class,

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
        ExportLandingPageAbility::class,
        ImportLandingPageAbility::class,
    ],

];
