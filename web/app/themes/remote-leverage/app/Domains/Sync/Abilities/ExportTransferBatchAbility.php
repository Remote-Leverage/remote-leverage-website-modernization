<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Datasets\Dataset;
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
 * verify — a dataset the registry does not mark transferable is refused here
 * too, by TransferManifest::fromArray().
 *
 * Two batch shapes, chosen by the dataset rather than by the caller:
 *
 *  - posts:  ['ok', 'posts', 'meta', 'last_id', 'done', 'total']
 *  - tables: ['ok', 'table', 'rows', 'last_id', 'done', 'total', 'tables']
 *
 * In both, `last_id` is the cursor to send back, `done` means this table (or
 * this dataset's posts) has nothing after the cursor, and `total` is populated
 * only on the opening batch. For the table shape the caller asks for a
 * `table_index`, never a table name: the name is resolved from the dataset
 * definition on this side, so no request can name a table of its own.
 *
 * Read-only. Nothing in this ability writes.
 */
class ExportTransferBatchAbility extends ReadOnlyTransferAbility
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
        return 'Returns one batch of rows for the given dataset — posts and meta, or whole table rows '
            .'for a table-backed dataset. Internal sync tooling only.';
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
        $limit = min(100, max(1, (int) ($input['limit'] ?? 25)));

        if ($this->registry->get($dataset)->isTableBacked()) {
            return $this->tableBatch($dataset, (int) ($input['table_index'] ?? 0), $after, $limit);
        }

        $ids = $this->exporter->postIdBatch(
            $manifest,
            $dataset,
            $after,
            $limit,
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

    /**
     * One batch of whole rows from the requested table of a table-backed dataset.
     *
     * @return array<string, mixed>
     */
    private function tableBatch(string $dataset, int $tableIndex, int $after, int $limit): array
    {
        $tables = $this->registry->get($dataset)->transferTables;

        if ($tableIndex < 0 || ! isset($tables[$tableIndex])) {
            // A bug in the caller's bookkeeping rather than a state to absorb:
            // reporting "done" here would let a pull skip a table and call the
            // dataset transferred.
            return ['ok' => false, 'error' => "Table index {$tableIndex} is not part of the \"{$dataset}\" dataset."];
        }

        $table = $tables[$tableIndex];
        $rows = $this->exporter->tableRowBatch($table, $after, $limit);

        // Counted only on the opening batch, like the post path: a COUNT per
        // chunk is a full scan for a number that does not change.
        $total = $after === 0 ? $this->exporter->tableCount($table) : null;

        if ($rows === []) {
            return ['ok' => true, 'table' => $table, 'rows' => [], 'last_id' => $after,
                'done' => true, 'total' => $total, 'tables' => count($tables)];
        }

        return [
            'ok' => true,
            'table' => $table,
            'rows' => $rows,
            'last_id' => (int) $rows[count($rows) - 1][Dataset::TRANSFER_KEY],
            'done' => false,
            'total' => $total,
            'tables' => count($tables),
        ];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'manifest' => ['type' => 'object', 'description' => 'The transfer manifest, re-validated here.'],
                'dataset' => ['type' => 'string', 'description' => 'Which selected dataset to read.'],
                'after' => ['type' => 'integer', 'description' => 'Cursor: return rows with an ID above this.'],
                'limit' => ['type' => 'integer', 'description' => 'Maximum rows in this batch (capped at 100).'],
                'table_index' => ['type' => 'integer', 'description' => 'Table-backed datasets only: which of the '
                    .'dataset\'s transfer tables to read, by position.'],
            ],
            'required' => ['manifest', 'dataset'],
        ];
    }
}
