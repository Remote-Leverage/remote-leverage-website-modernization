<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Sync\Commands\GrantSyncCapabilityCommand;
use App\Domains\Sync\Commands\SyncPageCommand;
use App\Domains\Sync\Commands\SyncSettingsCommand;
use Illuminate\Support\ServiceProvider;

class SyncServiceProvider extends ServiceProvider
{
    /**
     * Register the local↔remote settings/page sync WP-CLI commands.
     *
     * The sync abilities themselves (App\Domains\Sync\Abilities\*) are
     * registered like any other ability, via config/ai-wordpress.php.
     */
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncSettingsCommand::class,
                SyncPageCommand::class,
                GrantSyncCapabilityCommand::class,
            ]);
        }
    }
}
