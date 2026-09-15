<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Transfer\Import\ContentImporter;
use App\Domains\Sync\Transfer\Import\DatasetCleaner;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferSession;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Throwable;

/**
 * Applies one batch of rows to the target.
 *
 * Chunks are imported as they arrive rather than staged to disk and applied at
 * commit. Staging a ~28MB dump onto the target's filesystem and then replaying
 * it needs a long-running process, which is exactly what the receiving side
 * does not have — every step here has to fit inside one PHP request.
 *
 * Writes are upserts keyed on the primary key, so a chunk redelivered after a
 * dropped connection lands on the same rows rather than duplicating them.
 */
class ReceiveTransferChunkAbility extends TransferAbility
{
    public function __construct(
        private readonly SessionStore $sessions,
        private readonly ContentImporter $importer,
        private readonly DatasetCleaner $cleaner,
        private readonly UndoLogFactory $undoLogs,
    ) {}

    public function label(): string
    {
        return 'Receive Environment Transfer Chunk';
    }

    public function description(): string
    {
        return 'Imports one batch of rows into an open transfer session. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $session = $this->sessions->find((string) ($input['session_id'] ?? ''));

        if (! $session instanceof TransferSession) {
            return ['ok' => false, 'error' => 'Unknown transfer session.'];
        }

        if ($session->isFinished()) {
            return ['ok' => false, 'error' => 'This transfer session is already '.$session->state.'.'];
        }

        $dataset = (string) ($input['dataset'] ?? '');

        if (! $session->manifest->includes($dataset)) {
            return ['ok' => false, 'error' => "Dataset \"{$dataset}\" is not part of this transfer."];
        }

        $session->state = TransferSession::STATE_IMPORTING;
        $undo = $this->undoLogs->for($session->id);

        // Clean lazily, on the first chunk of each dataset, rather than when the
        // session opens. Deleting only once rows are actually arriving means a
        // transfer that fails before it sends anything leaves the target intact,
        // and it keeps each request bounded — emptying every selected dataset up
        // front is the single long request this design exists to avoid.
        if ($session->manifest->shouldClean($dataset) && ! $session->hasCleaned($dataset)) {
            try {
                $removed = $this->cleaner->clean($session, $dataset, $undo);
            } catch (Throwable $e) {
                $session->fail($e->getMessage());
                $this->sessions->save($session);

                return ['ok' => false, 'error' => $e->getMessage(), 'session' => $session->toStatusArray()];
            }

            $session->markCleaned($dataset);
            $session->recordRows($dataset, 'removed', $removed);

            // Persisted before a single row is imported. If the import below
            // dies and the chunk is redelivered, the retry must not clean a
            // second time — by then the target holds rows this transfer wrote.
            $this->sessions->save($session);
        }

        try {
            $written = $this->importer->importBatch(
                $session,
                $dataset,
                $this->rows($input, 'posts'),
                $this->rows($input, 'meta'),
                $undo,
            );
        } catch (Throwable $e) {
            $session->fail($e->getMessage());
            $this->sessions->save($session);

            return ['ok' => false, 'error' => $e->getMessage(), 'session' => $session->toStatusArray()];
        }

        $this->sessions->save($session);

        return ['ok' => true, 'written' => $written, 'session' => $session->toStatusArray()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $input, string $key): array
    {
        $rows = $input[$key] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'session_id' => ['type' => 'string', 'description' => 'Session opened by begin-transfer.'],
                'dataset' => ['type' => 'string', 'description' => 'Which selected dataset this batch belongs to.'],
                'posts' => ['type' => 'array', 'description' => 'Post rows in this batch.'],
                'meta' => ['type' => 'array', 'description' => 'Postmeta rows for those posts.'],
            ],
            'required' => ['session_id', 'dataset'],
        ];
    }
}
