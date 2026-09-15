<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\SyncEnvironment;
use RuntimeException;

/**
 * Deletes this environment's transfer history: session records and undo logs.
 *
 * Housekeeping, not recovery. It exists because sessions and their logs are the
 * only sync state that outlives a run, and after a stretch of failed attempts
 * there is no way to get back to a clean slate — the staging screen shows
 * history and restore, and neither of those removes anything.
 *
 * What it destroys matters: an undo log is the *only* thing that can revert a
 * transfer that already landed. Deleting one does not undo that transfer, it
 * makes it permanent. So this refuses to run while any session is still live,
 * and the caller is expected to have rolled back anything it still wanted back.
 */
final class TransferLogPurger
{
    public function __construct(
        private readonly SessionStore $sessions,
        private readonly UndoLogFactory $undoLogs,
    ) {}

    /**
     * @return array{sessions: int, undo_logs: int}
     */
    public function purge(): array
    {
        SyncEnvironment::assertSyncEnabled();

        // An unfinished session is either running right now or is holding the
        // undo log for rows already written. Clearing either case is how you
        // turn a recoverable mess into an unrecoverable one.
        $active = $this->sessions->active();

        if ($active !== null) {
            throw new RuntimeException(
                "Refusing to clear transfer logs: session {$active->id} is still \"{$active->state}\". "
                .'Roll it back or finish it first.'
            );
        }

        $sessions = 0;

        foreach ($this->sessions->ids() as $id) {
            $this->sessions->delete($id);
            $sessions++;
        }

        // Done after the records, and from the disk's own listing rather than
        // the index, so a log whose session record was already pruned away is
        // still found and removed.
        $logs = 0;

        foreach ($this->undoLogs->sessionIds() as $id) {
            $this->undoLogs->forget($id);
            $logs++;
        }

        return ['sessions' => $sessions, 'undo_logs' => $logs];
    }
}
