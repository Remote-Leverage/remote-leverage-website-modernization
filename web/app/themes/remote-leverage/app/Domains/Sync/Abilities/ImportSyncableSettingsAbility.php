<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\SyncCapability;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * REST-only (no meta.mcp.public) — invoked exclusively by `wp rl:sync:settings`.
 * Rejects any key not on the config/rl-sync.php whitelist rather than trusting
 * the caller's payload, since this writes directly to wp_options.
 */
class ImportSyncableSettingsAbility extends Ability
{
    public function label(): string
    {
        return 'Import Syncable Settings';
    }

    public function description(): string
    {
        return 'Writes the given key/value pairs into wp_options, restricted to the whitelist in '.
            'config/rl-sync.php. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $allowed = config('rl-sync.options', []);
        $values = $input['values'] ?? [];
        $updated = [];
        $rejected = [];

        foreach ($values as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                $rejected[] = $key;

                continue;
            }

            update_option($key, $value);
            $updated[] = $key;
        }

        return ['updated' => $updated, 'rejected' => $rejected];
    }

    public function permission(): bool|WP_Error
    {
        if (! SyncCapability::currentUserCan()) {
            return new WP_Error('forbidden', 'The '.SyncCapability::NAME.' capability is required.');
        }

        return true;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'values' => [
                    'type' => 'object',
                    'description' => 'Map of wp_options key to value. Keys not in the config/rl-sync.php '.
                        'whitelist are rejected rather than written.',
                ],
            ],
            'required' => ['values'],
        ];
    }

    public function category(): ?string
    {
        return 'site';
    }

    /**
     * See ExportSyncableSettingsAbility::meta() — show_in_rest (not `public`)
     * is what unlocks the REST run endpoint on WordPress 7.1+.
     */
    public function meta(): array
    {
        return ['show_in_rest' => true];
    }
}
