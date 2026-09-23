<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Lead\Export\LeadExportJobStore;
use App\Domains\Lead\Export\LeadExportRunner;
use App\Infrastructure\Console\Commands\ApplyWordfenceConfigCommand;
use App\Infrastructure\Console\Commands\PartnerSeedCommand;
use App\Infrastructure\Console\Commands\PruneIntegrationCallsCommand;
use App\Infrastructure\Console\Commands\RunDeployTasksCommand;
use App\Infrastructure\WordPress\Admin\CalendlyAdminDashboard;
use App\Infrastructure\WordPress\Admin\ContentAuditAdmin;
use App\Infrastructure\WordPress\Admin\DuplicatePostAdmin;
use App\Infrastructure\WordPress\Admin\LeadExportPanel;
use App\Infrastructure\WordPress\Admin\LeadsAdminDashboard;
use App\Infrastructure\WordPress\Admin\MarketingDashboard;
use App\Infrastructure\WordPress\Admin\PartnerHubAdmin;
use App\Infrastructure\WordPress\Admin\PixelDeferralAdmin;
use App\Infrastructure\WordPress\Admin\ReferralAdminDashboard;
use App\Infrastructure\WordPress\Admin\SecurityAdmin;
use App\Infrastructure\WordPress\Admin\Seo\SeoAdmin;
use App\Infrastructure\WordPress\Admin\SocialKitAdmin;
use App\Infrastructure\WordPress\Admin\WordPressAdminTheme;
use App\Infrastructure\WordPress\PostDuplicator;
use App\Infrastructure\WordPress\PostTypes\CaseStudyPostType;
use App\Infrastructure\WordPress\PostTypes\PartnerPostType;
use App\Infrastructure\WordPress\SocialKitAssets;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Domain service providers to register.
     *
     * @var array<class-string>
     */
    protected array $providers = [
        ReferralServiceProvider::class,
        SchedulingServiceProvider::class,
        TrackingServiceProvider::class,
        LeadServiceProvider::class,
        ContentAuditServiceProvider::class,
        LivewireServiceProvider::class,
        RouteServiceProvider::class,
        SyncServiceProvider::class,
        AiServiceProvider::class,
        ObservabilityServiceProvider::class,
        MarketingServiceProvider::class,
        ToolsServiceProvider::class,
    ];

    /**
     * Register domain services and dependencies.
     */
    public function register(): void
    {
        foreach ($this->providers as $provider) {
            $this->app->register($provider);
        }

        $this->app->singleton(PartnerPostType::class, fn () => new PartnerPostType);
        $this->app->singleton(CaseStudyPostType::class, fn () => new CaseStudyPostType);
        $this->app->singleton(LeadsAdminDashboard::class, fn () => new LeadsAdminDashboard);
        $this->app->singleton(LeadExportPanel::class, fn ($app) => new LeadExportPanel(
            $app->make(LeadExportJobStore::class),
            $app->make(LeadExportRunner::class),
        ));
        $this->app->singleton(WordPressAdminTheme::class, fn () => new WordPressAdminTheme);
        $this->app->singleton(MarketingDashboard::class, fn () => new MarketingDashboard);
        $this->app->singleton(ContentAuditAdmin::class, fn () => new ContentAuditAdmin);
        $this->app->singleton(PartnerHubAdmin::class, fn () => new PartnerHubAdmin);
        $this->app->singleton(ReferralAdminDashboard::class, fn () => new ReferralAdminDashboard);
        $this->app->singleton(CalendlyAdminDashboard::class, fn () => new CalendlyAdminDashboard);
        $this->app->singleton(SecurityAdmin::class, fn () => new SecurityAdmin);
        $this->app->singleton(SocialKitAssets::class, fn () => new SocialKitAssets);
        $this->app->singleton(SocialKitAdmin::class, fn () => new SocialKitAdmin);
        $this->app->singleton(PixelDeferralAdmin::class, fn () => new PixelDeferralAdmin);
        $this->app->singleton(SeoAdmin::class, fn () => new SeoAdmin);
        $this->app->singleton(DuplicatePostAdmin::class, fn ($app) => new DuplicatePostAdmin(
            $app->make(PostDuplicator::class),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunDeployTasksCommand::class,
                PartnerSeedCommand::class,
                ApplyWordfenceConfigCommand::class,
                PruneIntegrationCallsCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap domain adapters and custom post types.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->app->make(PartnerPostType::class)->register();
        $this->app->make(CaseStudyPostType::class)->register();
        $this->app->make(LeadsAdminDashboard::class)->register();
        $this->app->make(LeadExportPanel::class)->register();
        $this->app->make(WordPressAdminTheme::class)->register();
        $this->app->make(MarketingDashboard::class)->register();
        $this->app->make(ContentAuditAdmin::class)->register();
        $this->app->make(PartnerHubAdmin::class)->register();
        $this->app->make(ReferralAdminDashboard::class)->register();
        $this->app->make(CalendlyAdminDashboard::class)->register();
        $this->app->make(SecurityAdmin::class)->register();
        $this->app->make(SocialKitAssets::class)->register();
        $this->app->make(SocialKitAdmin::class)->register();
        $this->app->make(PixelDeferralAdmin::class)->register();
        $this->app->make(SeoAdmin::class)->register();
        $this->app->make(DuplicatePostAdmin::class)->register();
    }
}
