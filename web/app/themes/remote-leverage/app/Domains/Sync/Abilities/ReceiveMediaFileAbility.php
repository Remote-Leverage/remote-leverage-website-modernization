<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use App\Domains\Sync\Transfer\Media\MediaFileReceiver;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferSession;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Throwable;

/**
 * Receives one slice of one media file.
 *
 * Bound to a transfer session so the write is recorded in the same undo log as
 * the rows — rolling a transfer back restores the files it replaced along with
 * the database, rather than leaving the two out of step.
 */
class ReceiveMediaFileAbility extends TransferAbility
{
    public function __construct(
        private readonly SessionStore $sessions,
        private readonly MediaFileExporter $exporter,
        private readonly UndoLogFactory $undoLogs,
    ) {}

    public function label(): string
    {
        return 'Receive Media File';
    }

    public function description(): string
    {
        return 'Writes one chunk of a media file into the uploads directory. Internal sync tooling only.';
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

        $receiver = new MediaFileReceiver($this->exporter->baseDir());

        try {
            $result = $receiver->writeChunk(
                (string) ($input['path'] ?? ''),
                (int) ($input['offset'] ?? 0),
                (string) ($input['data'] ?? ''),
                (bool) ($input['final'] ?? false),
                (string) ($input['sha256'] ?? ''),
                $this->undoLogs->for($session->id),
            );
        } catch (Throwable $e) {
            // A bad path or a failed checksum fails this file, not the whole
            // transfer: the rows may be fine and the rest of the files may
            // still land, and the session stays rollback-able either way.
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if ($result['complete']) {
            $session->recordRows('media', 'files', 1);
            $this->sessions->save($session);
        }

        return ['ok' => true] + $result;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'session_id' => ['type' => 'string', 'description' => 'Session opened by begin-transfer.'],
                'path' => ['type' => 'string', 'description' => 'Path relative to the uploads directory.'],
                'offset' => ['type' => 'integer', 'description' => 'Byte offset this chunk starts at.'],
                'data' => ['type' => 'string', 'description' => 'Base64 encoded bytes.'],
                'final' => ['type' => 'boolean', 'description' => 'True on the last chunk of the file.'],
                'sha256' => ['type' => 'string', 'description' => 'Checksum of the whole file, verified on the last chunk.'],
            ],
            'required' => ['session_id', 'path'],
        ];
    }
}
