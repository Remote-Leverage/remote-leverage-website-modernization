<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\Push\PushJob;
use App\Domains\Sync\Transfer\Push\PushJobRunner;
use App\Domains\Sync\Transfer\Push\PushJobStore;
use RuntimeException;

/**
 * Runs a push to completion in one call.
 *
 * This is the CLI's entry point, and it is nothing more than a loop over
 * PushJobRunner::step(). The browser drives the identical steps one request at
 * a time; keeping both on the same runner means the UI and the command can
 * never drift into behaving differently.
 *
 * Safe here because the CLI has no proxy in front of it — the 504 that forced
 * this design only applies to the web path.
 */
class TransferPusher
{
    public function __construct(
        private readonly PushJobStore $jobs,
        private readonly PushJobRunner $runner,
    ) {}

    /**
     * @param  callable(string, array<string, mixed>): void|null  $onProgress
     * @return array<string, mixed>
     */
    public function push(TransferManifest $manifest, string $targetEnv, ?callable $onProgress = null): array
    {
        SyncEnvironment::assertSyncEnabled();
        $this->assertTargetIsNotProduction($targetEnv);

        if (! $manifest->isPush()) {
            throw new RuntimeException('TransferPusher only runs push manifests.');
        }

        $job = $this->jobs->create($manifest, $targetEnv);

        while (! $job->isFinished()) {
            $before = $job->phase;
            $job = $this->runner->step($job);
            $this->jobs->save($job);

            if ($onProgress !== null) {
                $onProgress($job->phase, $job->toStatusArray());
            }

            // A step that neither advanced the phase nor moved a cursor would
            // spin forever; the runner always does one of the two, so this only
            // fires if that contract is ever broken.
            if ($before === $job->phase && $job->phase === PushJob::PHASE_BEGIN) {
                throw new RuntimeException('Push made no progress; aborting.');
            }
        }

        if ($job->phase === PushJob::PHASE_FAILED) {
            throw new RuntimeException(
                $job->error."\nThe target still holds session {$job->sessionId}; roll it back to undo what landed."
            );
        }

        return [
            'session_id' => $job->sessionId,
            'sent' => $job->counters,
            'session' => $job->toStatusArray(),
            'undo_entries' => $job->counters['undo_entries'] ?? 0,
        ];
    }

    /**
     * Gate 3 of the four in docs/environment-sync.md §6.
     *
     * The target enforces its own refusal, but that only helps once the request
     * arrives. Checking the configured URL here means a mistyped target never
     * gets sent a single row of anything.
     */
    private function assertTargetIsNotProduction(string $targetEnv): void
    {
        if ($targetEnv === SyncEnvironment::PRODUCTION) {
            throw new RuntimeException('Refusing to push to production.');
        }

        $targetUrl = (string) config("rl-sync.environments.{$targetEnv}.url");
        $productionUrl = (string) config('rl-sync.environments.production.url');

        if ($targetUrl !== '' && $productionUrl !== '' && $this->sameHost($targetUrl, $productionUrl)) {
            throw new RuntimeException(
                "Refusing to push: \"{$targetEnv}\" is configured with the same host as production."
            );
        }
    }

    private function sameHost(string $a, string $b): bool
    {
        return strcasecmp((string) parse_url($a, PHP_URL_HOST), (string) parse_url($b, PHP_URL_HOST)) === 0;
    }
}
