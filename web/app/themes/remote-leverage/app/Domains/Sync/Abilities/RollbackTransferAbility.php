<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferSession;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Throwable;

/**
 * Puts a transfer back the way it found things.
 *
 * Works on a completed transfer as well as a failed one: a sync that succeeded
 * but brought across the wrong selection needs undoing just as much as one that
 * died mid-import.
 *
 * The undo log outlives the container that wrote it — it sits on the uploads
 * mount — so this can be called long after the request that failed is gone.
 */
class RollbackTransferAbility extends TransferAbility
{
    public function __construct(
        private readonly SessionStore $sessions,
        private readonly UndoLogFactory $undoLogs,
    ) {}

    public function label(): string
    {
        return 'Roll Back Environment Transfer';
    }

    public function description(): string
    {
        return 'Restores everything a transfer session overwrote or created. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $sessionId = (string) ($input['session_id'] ?? '');
        $session = $this->sessions->find($sessionId);

        if (! $session instanceof TransferSession) {
            return ['ok' => false, 'error' => 'Unknown transfer session.'];
        }

        $log = $this->undoLogs->for($session->id);

        // An empty log is not an error, and refusing here used to wedge the
        // whole tool: a session that died between begin and its first write has
        // nothing to undo, so rollback bailed without closing it, and every
        // later transfer was refused by a session no one could clear. Closing
        // it is the only thing left to do, and it is safe precisely because
        // nothing was written.
        if (! $log->exists()) {
            $session->fail('Rolled back: nothing had been written.');
            $this->sessions->save($session);

            return ['ok' => true, 'reverted' => 0, 'session' => $session->toStatusArray()];
        }

        try {
            $applied = $log->rollback();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Rollback failed: '.$e->getMessage()];
        }

        // Discarded only once the restore has actually succeeded — a log kept
        // after a half-finished rollback can be replayed, and replaying is
        // harmless because every entry restores an absolute prior value rather
        // than applying a delta.
        $log->discard();

        $session->fail('Rolled back: '.$applied.' change(s) reverted.');
        $this->sessions->save($session);

        return ['ok' => true, 'reverted' => $applied, 'session' => $session->toStatusArray()];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'session_id' => ['type' => 'string', 'description' => 'Session to undo.'],
            ],
            'required' => ['session_id'],
        ];
    }
}
