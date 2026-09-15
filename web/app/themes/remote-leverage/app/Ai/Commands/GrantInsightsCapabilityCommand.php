<?php

declare(strict_types=1);

namespace App\Ai\Commands;

use App\Ai\InsightsCapability;
use Illuminate\Console\Command;

class GrantInsightsCapabilityCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:ai:grant-insights {user_login : The WordPress user to grant lead-data reads to}
        {--revoke : Remove the capability instead of granting it}';

    /**
     * @var string
     */
    protected $description = 'Grant (or revoke) the '.InsightsCapability::NAME.' capability, which lets a user '.
        'read real lead data through the app/query-leads and app/lead-stats abilities. Run once per environment '.
        'for the user an MCP client authenticates as.';

    public function handle(): int
    {
        $login = $this->argument('user_login');
        $user = get_user_by('login', $login);

        if ($user === false) {
            $this->error("No user found with login \"{$login}\".");

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->remove_cap(InsightsCapability::NAME);
            $this->info('Revoked '.InsightsCapability::NAME." from {$user->user_login}.");

            return self::SUCCESS;
        }

        if ($user->has_cap(InsightsCapability::NAME)) {
            $this->info("{$user->user_login} already has the ".InsightsCapability::NAME.' capability.');

            return self::SUCCESS;
        }

        $user->add_cap(InsightsCapability::NAME);

        $this->info('Granted '.InsightsCapability::NAME." to {$user->user_login}.");
        $this->warn('This user can now read customer contact details (name, email, phone) through MCP.');

        return self::SUCCESS;
    }
}
