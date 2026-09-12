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

        // Refuse to open a second session while one is still running. This is
        // the guard that matters most, because it holds however the transfer was
        // started — a stale browser tab, a CLI run, or a second operator — and
        // it is the one whose absence let abandoned "importing" sessions pile up.
        $running = $this->sessions->active();

        if ($running !== null && ($input['force'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => 'A transfer is already in progress on this environment (session '
                    .$running->id.', '.$running->state.'). Finish, roll back, or cancel it first.',
                'blocking_session' => $running->toStatusArray(),
            ];
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
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Open a session even though another is still running. '
                        .'Only for recovering from an abandoned transfer.',
                ],
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
