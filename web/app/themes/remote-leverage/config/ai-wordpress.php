<?php

use App\Ai\Abilities\CreateLandingPageAbility;
use App\Ai\Abilities\ListPatternsAbility;
use App\Ai\Abilities\UpdateLandingPageContentAbility;
use App\Domains\Sync\Abilities\ExportLandingPageAbility;
use App\Domains\Sync\Abilities\ExportSyncableSettingsAbility;
use App\Domains\Sync\Abilities\ImportLandingPageAbility;
use App\Domains\Sync\Abilities\ImportSyncableSettingsAbility;

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
        ExportLandingPageAbility::class,
        ImportLandingPageAbility::class,
    ],

];
