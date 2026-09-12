<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Sync\Commands\GrantSyncCapabilityCommand;
use App\Domains\Sync\Commands\PullTransferCommand;
use App\Domains\Sync\Commands\PurgeDatasetCommand;
use App\Domains\Sync\Commands\PushTransferCommand;
use App\Domains\Sync\Commands\RollbackLocalCommand;
use App\Domains\Sync\Commands\RollbackTransferCommand;
use App\Domains\Sync\Commands\SyncPageCommand;
use App\Domains\Sync\Commands\SyncSettingsCommand;
use App\Domains\Sync\Provisioning\SyncCredentialProvisioner;
use App\Infrastructure\WordPress\Admin\EnvironmentSyncAdmin;
use App\Infrastructure\WordPress\Admin\EnvironmentSyncScreen;
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
                PushTransferCommand::class,
                PullTransferCommand::class,
                PurgeDatasetCommand::class,
                RollbackLocalCommand::class,
                RollbackTransferCommand::class,
            ]);
        }

        $this->app->singleton(SyncCredentialProvisioner::class, fn () => new SyncCredentialProvisioner);

        // Autowired rather than hand-constructed: the screen's dependencies grow
        // as the feature does, and every one of them (registry, session store,
        // exporter, pusher) is resolvable without configuration.
        $this->app->singleton(EnvironmentSyncScreen::class);
        $this->app->singleton(EnvironmentSyncAdmin::class);
    }

    /**
     * Bootstrap the Settings → Environment Sync screen.
     *
     * The screen gates itself on SyncEnvironment::syncEnabled(), so this is safe
     * to call unconditionally — it registers no hooks in production.
     */
    public function boot(): void
    {
        $this->app->make(EnvironmentSyncAdmin::class)->register();
    }
}
