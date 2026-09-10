<?php

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
        \App\Ai\Abilities\ListPatternsAbility::class,
        \App\Ai\Abilities\CreateLandingPageAbility::class,
        \App\Ai\Abilities\UpdateLandingPageContentAbility::class,

        // Local↔remote settings/page sync — REST-only, invoked by wp rl:sync:*.
        \App\Domains\Sync\Abilities\ExportSyncableSettingsAbility::class,
        \App\Domains\Sync\Abilities\ImportSyncableSettingsAbility::class,
        \App\Domains\Sync\Abilities\ExportLandingPageAbility::class,
        \App\Domains\Sync\Abilities\ImportLandingPageAbility::class,
    ],

];
