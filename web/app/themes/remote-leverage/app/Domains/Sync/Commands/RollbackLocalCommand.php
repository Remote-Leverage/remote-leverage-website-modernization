<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Illuminate\Console\Command;

/**
 * Undoes a transfer this environment received — which for a pull is this one.
 * The remote equivalent is rl:sync:rollback, which asks the target to undo
 * a push instead.
 */
class RollbackLocalCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:sync:rollback-local {session : Session ID reported by rl:sync:pull}';

    /**
     * @var string
     */
    protected $description = 'Undo a transfer that was imported into this environment.';

    public function handle(SessionStore $sessions, UndoLogFactory $undoLogs): int
    {
        $id = (string) $this->argument('session');
        $session = $sessions->find($id);

        if ($session === null) {
            $this->error("Unknown session: {$id}");

            return self::FAILURE;
        }

        $log = $undoLogs->for($session->id);

        if (! $log->exists()) {
            $this->error('This session has nothing recorded to roll back.');

            return self::FAILURE;
        }

        if (! $this->confirm("Revert {$log->entryCount()} change(s) on this environment?", false)) {
            return self::SUCCESS;
        }

        $applied = $log->rollback();
        $log->discard();

        $session->fail('Rolled back: '.$applied.' change(s) reverted.');
        $sessions->save($session);

        $this->info("Reverted {$applied} change(s).");

        return self::SUCCESS;
    }
}
