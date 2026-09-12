<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\InvalidManifestException;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;

/**
 * Opens a transfer session on the receiving environment.
 *
 * The manifest arrives from the other environment and is validated here rather
 * than trusted — TransferManifest refuses a purge-only dataset outright, so a
 * caller asking to import leads or users is rejected at the door even though it
 * authenticated successfully.
 */
class BeginTransferAbility extends TransferAbility
{
    public function __construct(
        private readonly SessionStore $sessions,
        private readonly DatasetRegistry $registry,
    ) {}

    public function label(): string
    {
        return 'Begin Environment Transfer';
    }

    public function description(): string
    {
        return 'Opens a session to receive a dataset transfer from another environment. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        try {
            $manifest = TransferManifest::fromArray((array) ($input['manifest'] ?? []), $this->registry);
        } catch (InvalidManifestException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $session = $this->sessions->create($manifest);

        return [
            'ok' => true,
            'session' => $session->toStatusArray(),
            'import_order' => $this->registry->importOrder($manifest->datasets),
        ];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'manifest' => [
                    'type' => 'object',
                    'description' => 'Transfer manifest: direction, datasets, exclusions and '
                        .'clean_before_import flags. Purge-only datasets are rejected.',
                ],
            ],
            'required' => ['manifest'],
        ];
    }
}
