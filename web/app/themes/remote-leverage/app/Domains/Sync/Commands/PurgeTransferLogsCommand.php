<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Domains\Sync\SyncClient;
use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\TransferLogPurger;
use Illuminate\Console\Command;
use Throwable;

class PurgeTransferLogsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:sync:purge-logs
        {--on=local : "local", or a remote environment name}';

    /**
     * @var string
     */
    protected $description = 'Delete every transfer session record and undo log on an environment.';

    public function handle(TransferLogPurger $purger): int
    {
        $on = (string) $this->option('on');

        return $on === 'local'
            ? $this->purgeLocally($purger)
            : $this->purgeRemotely($on);
    }

    private function purgeLocally(TransferLogPurger $purger): int
    {
        $env = SyncEnvironment::current();

        $this->warnAboutUndo($env);

        if ($this->ask("Type \"{$env}\" to confirm") !== $env) {
            $this->info('Confirmation did not match. Nothing was deleted.');

            return self::SUCCESS;
        }

        try {
            $this->report($purger->purge());
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function purgeRemotely(string $target): int
    {
        $this->warnAboutUndo($target);

        if ($this->ask("Type \"{$target}\" to confirm") !== $target) {
            $this->info('Confirmation did not match. Nothing was deleted.');

            return self::SUCCESS;
        }

        try {
            $result = (new SyncClient($target))->run('app/purge-transfer-logs', [
                'confirm_environment' => $target,
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (($result['ok'] ?? false) !== true) {
            $this->error((string) ($result['error'] ?? 'Purge failed.'));

            return self::FAILURE;
        }

        $this->report($result);

        return self::SUCCESS;
    }

    /**
     * Say what is actually being given up, which is not the history.
     */
    private function warnAboutUndo(string $env): void
    {
        $this->warn("This deletes every transfer session record and undo log on {$env}.");
        $this->warn('Any transfer that already landed there becomes permanent: the undo log is '
            .'the only thing that can revert one. Roll back anything you still want back first.');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result): void
    {
        $this->info(sprintf(
            'Deleted %d session record(s) and %d undo log(s).',
            (int) ($result['sessions'] ?? 0),
            (int) ($result['undo_logs'] ?? 0),
        ));
    }
}
