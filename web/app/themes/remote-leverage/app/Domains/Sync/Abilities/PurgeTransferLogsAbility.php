<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\TransferLogPurger;
use Throwable;

/**
 * Clears this environment's transfer history over the sync channel.
 *
 * The remote has no shell, so without this there is no way to clean up its
 * sessions and undo logs at all — they can only be read, never removed. That
 * gap is how a run of failed pushes left staging holding stale sessions that
 * refused every later transfer and could not be cleared from anywhere.
 *
 * Confirmation works like PurgeDatasetAbility's: the capability check gates who
 * may call it, the confirmation string gates *which environment* the caller
 * believed they were addressing, which is the mistake that actually happens.
 */
class PurgeTransferLogsAbility extends TransferAbility
{
    public function __construct(private readonly TransferLogPurger $purger) {}

    public function label(): string
    {
        return 'Purge Transfer Logs';
    }

    public function description(): string
    {
        return 'Deletes every transfer session record and undo log on this environment. '
            .'Transfers already applied become permanent. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $confirm = (string) ($input['confirm_environment'] ?? '');
        $here = SyncEnvironment::current();

        if ($confirm !== $here) {
            return [
                'ok' => false,
                'error' => "Confirmation mismatch: this environment is \"{$here}\", "
                    ."the request confirmed \"{$confirm}\". Nothing was deleted.",
            ];
        }

        try {
            $deleted = $this->purger->purge();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'environment' => $here] + $deleted;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'confirm_environment' => [
                    'type' => 'string',
                    'description' => 'Must equal the target environment\'s WP_ENV, as a guard against '
                        .'clearing the wrong environment\'s history.',
                ],
            ],
            'required' => ['confirm_environment'],
        ];
    }
}
