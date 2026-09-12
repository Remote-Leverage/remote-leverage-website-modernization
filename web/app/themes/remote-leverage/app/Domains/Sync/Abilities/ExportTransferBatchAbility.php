<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Export\ContentExporter;
use App\Domains\Sync\Transfer\InvalidManifestException;
use App\Domains\Sync\Transfer\TransferManifest;

/**
 * Reads one batch of rows out of this environment, for a pull.
 *
 * The mirror of receive-transfer-chunk: there the remote is the target and this
 * environment sends, here the remote is the source and this environment is
 * asked. The manifest is re-validated on arrival even though the puller built
 * it, because "the caller already checked" is not a property this side can
 * verify — a request for the leads dataset is refused here too.
 *
 * Read-only. Nothing in this ability writes.
 */
class ExportTransferBatchAbility extends TransferAbility
{
    public function __construct(
        private readonly DatasetRegistry $registry,
        private readonly ContentExporter $exporter,
    ) {}

    public function label(): string
    {
        return 'Export Environment Transfer Batch';
    }

    public function description(): string
    {
        return 'Returns one batch of post and meta rows for the given dataset. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        try {
            $manifest = TransferManifest::fromArray((array) ($input['manifest'] ?? []), $this->registry);
        } catch (InvalidManifestException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $dataset = (string) ($input['dataset'] ?? '');

        if (! $manifest->includes($dataset)) {
            return ['ok' => false, 'error' => "Dataset \"{$dataset}\" is not part of this transfer."];
        }

        $after = max(0, (int) ($input['after'] ?? 0));

        $ids = $this->exporter->postIdBatch(
            $manifest,
            $dataset,
            $after,
            min(100, max(1, (int) ($input['limit'] ?? 25))),
        );

        // Counted only on the opening batch: the puller needs a denominator for
        // its progress bar, and repeating the COUNT on every batch would add a
        // full table scan per chunk for a number that does not change.
        $total = $after === 0 ? $this->exporter->count($manifest, $dataset) : null;

        if ($ids === []) {
            return ['ok' => true, 'posts' => [], 'meta' => [], 'last_id' => 0, 'done' => true,
                'total' => $total];
        }

        return [
            'ok' => true,
            'posts' => $this->exporter->postRows($ids),
            'meta' => $this->exporter->postMetaRows($ids),
            'last_id' => (int) end($ids),
            'done' => false,
            'total' => $total,
        ];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'manifest' => ['type' => 'object', 'description' => 'The transfer manifest, re-validated here.'],
                'dataset' => ['type' => 'string', 'description' => 'Which selected dataset to read.'],
                'after' => ['type' => 'integer', 'description' => 'Cursor: return posts with an ID above this.'],
                'limit' => ['type' => 'integer', 'description' => 'Maximum posts in this batch (capped at 100).'],
            ],
            'required' => ['manifest', 'dataset'],
        ];
    }
}
