<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\SyncCapability;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * Shared gate for the transfer abilities that only ever *read*.
 *
 * These are the three a pull invokes on the far side — export-transfer-batch,
 * export-media-manifest, read-media-file — and they are the one part of the
 * transfer engine production is allowed to serve.
 *
 * ## Why this class exists
 *
 * Gate 2 (TransferAbility::permission(), docs/environment-sync.md §6) refuses on
 * WP_ENV === 'production' without asking whether the call would write anything.
 * That is the right default and it stays the default: every ability that mutates
 * the site still extends TransferAbility and still refuses. But applied to the
 * export side it also made production unusable as a *source*, which is a
 * different thing from protecting it as a target, and it is why leads could
 * never be pulled down to test the marketing cost alert against real volumes.
 *
 * ## What still protects production
 *
 * - Nothing here can write. The three subclasses read rows, list attachments and
 *   stream a file; none of them touch the database or the filesystem.
 * - The capability check is unchanged, and on production `sync-service` holds
 *   `rl_manage_ai_sync` only if an admin explicitly pressed Generate on the
 *   provisioning screen. It is not granted by deploy.
 * - SyncClient still refuses to *target* production, so no push, purge or
 *   rollback can name it however the credentials are configured.
 * - The receiving side of every transfer is still gated by TransferAbility, so
 *   production cannot be written to even by a caller holding the capability.
 *
 * ## What this does widen
 *
 * A credential that leaks now reads production data rather than nothing —
 * including unredacted lead PII, which is the whole point of the leads dataset
 * (see §2). That is the accepted trade, not an oversight. Revoke on the
 * provisioning screen is the kill switch, and it drops the capability as well as
 * the password.
 */
abstract class ReadOnlyTransferAbility extends Ability
{
    public function permission(): bool|WP_Error
    {
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
     * Mirrors TransferAbility::meta() — show_in_rest unlocks the REST run
     * endpoint without putting these into MCP's public auto-discovery.
     */
    public function meta(): array
    {
        return ['show_in_rest' => true];
    }
}
