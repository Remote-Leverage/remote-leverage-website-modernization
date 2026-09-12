<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\Pull\PullJob;
use App\Domains\Sync\Transfer\Pull\PullJobRunner;
use App\Domains\Sync\Transfer\Pull\PullJobStore;
use RuntimeException;

/**
 * Runs a pull to completion in one call — the CLI's entry point.
 *
 * Like TransferPusher, this is only a loop over the runner's steps. The browser
 * takes the same steps one request at a time, so the two paths cannot diverge.
 */
class TransferPuller
{
    public function __construct(
        private readonly PullJobStore $jobs,
        private readonly PullJobRunner $runner,
        private readonly UndoLogFactory $undoLogs,
    ) {}

    /**
     * @param  callable(string, array<string, mixed>): void|null  $onProgress
     * @return array<string, mixed>
     */
    public function pull(TransferManifest $manifest, string $sourceEnv, ?callable $onProgress = null): array
    {
        SyncEnvironment::assertSyncEnabled();

        if ($manifest->isPush()) {
            throw new RuntimeException('TransferPuller only runs pull manifests.');
        }

        $job = $this->jobs->create($manifest, $sourceEnv);

        while (! $job->isFinished()) {
            $job = $this->runner->step($job);
            $this->jobs->save($job);

            if ($onProgress !== null) {
                $onProgress($job->phase, $job->toStatusArray());
            }
        }

        if ($job->phase === PullJob::PHASE_FAILED) {
            throw new RuntimeException(
                $job->error."\nRoll back locally with: wp acorn rl:sync:rollback-local {$job->sessionId}"
            );
        }

        return [
            'session_id' => $job->sessionId,
            'session' => $job->toStatusArray(),
            'undo_entries' => $this->undoLogs->for($job->sessionId)->entryCount(),
        ];
    }
}
