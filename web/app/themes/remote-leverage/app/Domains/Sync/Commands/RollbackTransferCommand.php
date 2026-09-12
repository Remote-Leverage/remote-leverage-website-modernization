<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Domains\Sync\SyncClient;
use Illuminate\Console\Command;
use Throwable;

class RollbackTransferCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:sync:rollback
        {session : Session ID reported by rl:sync:push}
        {--target=staging : Remote environment the session lives on}';

    /**
     * @var string
     */
    protected $description = 'Undo everything a transfer session wrote on the target.';

    public function handle(): int
    {
        $target = (string) $this->option('target');
        $session = (string) $this->argument('session');

        if (! $this->confirm("Roll back session {$session} on {$target}?", false)) {
            return self::SUCCESS;
        }

        try {
            $result = (new SyncClient($target))->run('app/rollback-transfer', ['session_id' => $session]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (($result['ok'] ?? false) !== true) {
            $this->error((string) ($result['error'] ?? 'Rollback failed.'));

            return self::FAILURE;
        }

        $this->info("Reverted {$result['reverted']} change(s) on {$target}.");

        return self::SUCCESS;
    }
}
