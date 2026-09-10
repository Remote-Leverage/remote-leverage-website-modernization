<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Domains\Sync\SyncCapability;
use Illuminate\Console\Command;

class GrantSyncCapabilityCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:sync:grant {user_login : The dedicated sync-service WordPress user}';

    /**
     * @var string
     */
    protected $description = 'One-time setup: grant the '.SyncCapability::NAME.' capability to the '.
        'dedicated sync-service user on this environment. Run once per environment after creating that user '.
        'and its Application Password.';

    public function handle(): int
    {
        $user = get_user_by('login', $this->argument('user_login'));

        if ($user === false) {
            $this->error("No user found with login \"{$this->argument('user_login')}\".");

            return self::FAILURE;
        }

        if ($user->has_cap(SyncCapability::NAME)) {
            $this->info("{$user->user_login} already has the ".SyncCapability::NAME.' capability.');

            return self::SUCCESS;
        }

        $user->add_cap(SyncCapability::NAME);
        $this->info('Granted '.SyncCapability::NAME." to {$user->user_login}.");

        return self::SUCCESS;
    }
}
