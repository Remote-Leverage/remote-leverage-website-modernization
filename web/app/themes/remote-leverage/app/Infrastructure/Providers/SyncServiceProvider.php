<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Sync\Commands\GrantSyncCapabilityCommand;
use App\Domains\Sync\Commands\SyncPageCommand;
use App\Domains\Sync\Commands\SyncSettingsCommand;
use App\Domains\Sync\Provisioning\SyncCredentialProvisioner;
use App\Infrastructure\WordPress\Admin\EnvironmentSyncAdmin;
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

        $this->app->singleton(SyncCredentialProvisioner::class, fn () => new SyncCredentialProvisioner);
        $this->app->singleton(
            EnvironmentSyncAdmin::class,
            fn ($app) => new EnvironmentSyncAdmin($app->make(SyncCredentialProvisioner::class)),
        );
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
