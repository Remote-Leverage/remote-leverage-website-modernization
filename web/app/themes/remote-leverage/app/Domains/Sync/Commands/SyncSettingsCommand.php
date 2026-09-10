<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Domains\Sync\SyncClient;
use Illuminate\Console\Command;

class SyncSettingsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:sync:settings {--push : Push local values to the remote environment}
        {--pull : Pull remote values down to this (local) environment}
        {--env=staging : Target environment key from config/rl-sync.php}';

    /**
     * @var string
     */
    protected $description = 'Push or pull the whitelisted settings in config/rl-sync.php '.
        '(Calendly tokens, webhook URLs, etc.) between this environment and a remote one.';

    public function handle(): int
    {
        $push = (bool) $this->option('push');
        $pull = (bool) $this->option('pull');
        $env = (string) $this->option('env');

        if ($push === $pull) {
            $this->error('Pass exactly one of --push or --pull.');

            return self::FAILURE;
        }

        $client = new SyncClient($env);
        $keys = config('rl-sync.options', []);

        if ($push) {
            $values = [];
            foreach ($keys as $key) {
                $values[$key] = get_option($key, null);
            }

            $result = $client->run('app/import-syncable-settings', ['values' => $values]);

            $this->info("Pushed to {$env}. Updated: ".implode(', ', $result['updated'] ?? []));
            if (! empty($result['rejected'])) {
                $this->warn('Rejected (not on whitelist remotely): '.implode(', ', $result['rejected']));
            }

            return self::SUCCESS;
        }

        $remoteValues = $client->run('app/export-syncable-settings');

        foreach ($keys as $key) {
            if (! array_key_exists($key, $remoteValues)) {
                continue;
            }

            $local = get_option($key, null);
            $remote = $remoteValues[$key];

            if ($local === $remote) {
                $this->line("  {$key}: unchanged");

                continue;
            }

            update_option($key, $remote);
            $this->info("  {$key}: updated from {$env}");
        }

        return self::SUCCESS;
    }
}
