<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferSession;
use App\Domains\Sync\Transfer\UndoLogFactory;

/**
 * Closes a transfer session and reports what landed.
 *
 * Also the status endpoint: called with no "complete" flag it just returns
 * progress, which is what the admin screen polls while a transfer runs.
 */
class FinishTransferAbility extends TransferAbility
{
    public function __construct(
        private readonly SessionStore $sessions,
        private readonly UndoLogFactory $undoLogs,
    ) {}

    public function label(): string
    {
        return 'Finish Environment Transfer';
    }

    public function description(): string
    {
        return 'Reports progress for a transfer session, and closes it when complete. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $session = $this->sessions->find((string) ($input['session_id'] ?? ''));

        if (! $session instanceof TransferSession) {
            return ['ok' => false, 'error' => 'Unknown transfer session.'];
        }

        if (($input['complete'] ?? false) === true && ! $session->isFinished()) {
            $session->complete();
            $this->flushCaches();
            $this->sessions->save($session);
        }

        // The log is kept after a successful transfer, not discarded: undoing a
        // transfer that worked but brought the wrong thing is as useful as
        // undoing one that failed. It ages out with the session.
        return [
            'ok' => true,
            'session' => $session->toStatusArray(),
            'undo_entries' => $this->undoLogs->for($session->id)->entryCount(),
        ];
    }

    /**
     * Imported rows were written straight to the database, so anything cached
     * from before the transfer — object cache, rewrite rules derived from the
     * imported posts — is now stale.
     */
    private function flushCaches(): void
    {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'session_id' => ['type' => 'string', 'description' => 'Session opened by begin-transfer.'],
                'complete' => [
                    'type' => 'boolean',
                    'description' => 'Close the session and flush caches. Omit to poll progress only.',
                ],
            ],
            'required' => ['session_id'],
        ];
    }
}
