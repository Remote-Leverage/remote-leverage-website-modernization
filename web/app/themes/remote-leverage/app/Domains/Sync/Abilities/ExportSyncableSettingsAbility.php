<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\SyncCapability;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * REST-only (no meta.mcp.public) — invoked exclusively by `wp rl:sync:settings`
 * via an authenticated HTTP call, never by an MCP client. See config/rl-sync.php
 * for the option whitelist this reads from.
 */
class ExportSyncableSettingsAbility extends Ability
{
    public function label(): string
    {
        return 'Export Syncable Settings';
    }

    public function description(): string
    {
        return 'Returns the current values of the whitelisted, environment-specific wp_options rows '.
            '(Calendly tokens, webhook URLs, etc.) defined in config/rl-sync.php. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $values = [];

        foreach (config('rl-sync.options', []) as $key) {
            $values[$key] = get_option($key, null);
        }

        return $values;
    }

    public function permission(): bool|WP_Error
    {
        if (! SyncCapability::currentUserCan()) {
            return new WP_Error('forbidden', 'The '.SyncCapability::NAME.' capability is required.');
        }

        return true;
    }

    public function category(): ?string
    {
        return 'site';
    }
}
