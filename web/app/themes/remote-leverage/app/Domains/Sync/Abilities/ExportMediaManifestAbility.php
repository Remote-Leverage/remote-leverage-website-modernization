<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Export\ContentExporter;
use App\Domains\Sync\Transfer\InvalidManifestException;
use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use App\Domains\Sync\Transfer\TransferManifest;

/**
 * Lists the media files behind this environment's attachments, for a pull.
 *
 * Returns paths with sizes and checksums so the puller can ask for only what it
 * is missing — the same negotiation a push does, with the roles swapped.
 *
 * Read-only.
 */
class ExportMediaManifestAbility extends TransferAbility
{
    public function __construct(
        private readonly DatasetRegistry $registry,
        private readonly ContentExporter $exporter,
        private readonly MediaFileExporter $files,
    ) {}

    public function label(): string
    {
        return 'Export Media Manifest';
    }

    public function description(): string
    {
        return 'Returns the paths, sizes and checksums of this environment\'s media files. '
            .'Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        try {
            $manifest = TransferManifest::fromArray((array) ($input['manifest'] ?? []), $this->registry);
        } catch (InvalidManifestException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! $manifest->includes(DatasetRegistry::MEDIA)) {
            return ['ok' => false, 'error' => 'Media is not part of this transfer.'];
        }

        $ids = [];
        $after = 0;

        while (($batch = $this->exporter->postIdBatch($manifest, DatasetRegistry::MEDIA, $after, 100)) !== []) {
            $ids = array_merge($ids, $batch);
            $after = (int) end($batch);
        }

        return ['ok' => true, 'files' => $this->files->manifest($this->files->pathsFor($ids))];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'manifest' => ['type' => 'object', 'description' => 'The transfer manifest, re-validated here.'],
            ],
            'required' => ['manifest'],
        ];
    }
}
