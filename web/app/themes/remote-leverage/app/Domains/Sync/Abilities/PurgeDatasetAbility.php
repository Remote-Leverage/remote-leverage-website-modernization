<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\DatasetPurger;
use Throwable;

/**
 * Empties a purgeable dataset on this environment.
 *
 * Requires an explicit confirmation string matching this environment's WP_ENV.
 * The capability check already gates who may call it; the confirmation gates
 * *which environment* the caller believed they were talking to, which is the
 * mistake that actually happens — pointing a purge at the wrong target.
 */
class PurgeDatasetAbility extends TransferAbility
{
    public function __construct(private readonly DatasetPurger $purger) {}

    public function label(): string
    {
        return 'Purge Dataset';
    }

    public function description(): string
    {
        return 'Deletes every row in a purgeable dataset (leads, referrals, scheduling) on this '
            .'environment. Not undoable. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $dataset = (string) ($input['dataset'] ?? '');
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
            $deleted = $this->purger->purge($dataset);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'environment' => $here, 'deleted' => $deleted];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dataset' => ['type' => 'string', 'description' => 'leads, referrals or scheduling.'],
                'confirm_environment' => [
                    'type' => 'string',
                    'description' => 'Must equal the target environment\'s WP_ENV, as a guard against '
                        .'purging the wrong environment.',
                ],
            ],
            'required' => ['dataset', 'confirm_environment'],
        ];
    }
}
