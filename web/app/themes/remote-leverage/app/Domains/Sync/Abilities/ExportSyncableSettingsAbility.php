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

    /**
     * See ListPatternsAbility::inputSchema() — a non-empty schema is required
     * even for a no-parameter ability, or acorn-ai's execute_callback wrapper
     * gets called with zero arguments and throws.
     */
    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function category(): ?string
    {
        return 'site';
    }

    /**
     * show_in_rest (not the broader "public") is what WordPress 7.1 requires
     * for the /wp-abilities/v1/.../run REST endpoint to even consider this
     * ability — otherwise it 404s before our own permission() check runs.
     * Leaving `public` unset keeps this out of general ability listings and
     * MCP's default-server auto-discovery (which is keyed off `public`).
     */
    public function meta(): array
    {
        return ['show_in_rest' => true];
    }
}
