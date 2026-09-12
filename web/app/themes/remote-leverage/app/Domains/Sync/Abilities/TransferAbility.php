<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\SyncCapability;
use App\Domains\Sync\SyncEnvironment;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * Shared gate for the environment-transfer abilities.
 *
 * This is gate 2 of the four in docs/environment-sync.md §6, and the one that
 * matters most: it refuses on the *receiving* environment, by its own WP_ENV,
 * regardless of who is calling or what capability they hold. Copying this code
 * to production along with the credentials and the config still gets you
 * nothing, because production refuses itself.
 *
 * The environment check deliberately runs before the capability check, so a
 * production install never even reports whether the caller was authenticated.
 */
abstract class TransferAbility extends Ability
{
    public function permission(): bool|WP_Error
    {
        if (! SyncEnvironment::syncEnabled()) {
            return new WP_Error(
                'rl_sync_disabled',
                'Environment sync is not available in the "'.SyncEnvironment::current().'" environment.',
                ['status' => 403],
            );
        }

        if (! SyncCapability::currentUserCan()) {
            return new WP_Error(
                'rl_sync_forbidden',
                'The '.SyncCapability::NAME.' capability is required.',
                ['status' => 403],
            );
        }

        return true;
    }

    public function category(): ?string
    {
        return 'site';
    }

    /**
     * show_in_rest (without the broader `public` flag) is what unlocks the REST
     * run endpoint on WordPress 7.1+ while keeping these out of MCP's
     * public-keyed auto-discovery — same reasoning as the settings abilities.
     */
    public function meta(): array
    {
        return ['show_in_rest' => true];
    }
}
