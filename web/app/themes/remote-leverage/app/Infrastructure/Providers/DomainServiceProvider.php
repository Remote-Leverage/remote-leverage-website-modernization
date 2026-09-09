<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\WordPress\Admin\ContentAuditAdmin;
use App\Infrastructure\WordPress\Admin\LeadsAdminDashboard;
use App\Infrastructure\WordPress\Admin\MarketingDashboard;
use App\Infrastructure\WordPress\Admin\PartnerHubAdmin;
use App\Infrastructure\WordPress\Admin\ReferralAdminDashboard;
use App\Infrastructure\WordPress\Admin\WordPressAdminTheme;
use App\Infrastructure\WordPress\PostTypes\PartnerPostType;
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
        $this->app->singleton(LeadsAdminDashboard::class, fn () => new LeadsAdminDashboard);
        $this->app->singleton(WordPressAdminTheme::class, fn () => new WordPressAdminTheme);
        $this->app->singleton(MarketingDashboard::class, fn () => new MarketingDashboard);
        $this->app->singleton(ContentAuditAdmin::class, fn () => new ContentAuditAdmin);
        $this->app->singleton(PartnerHubAdmin::class, fn () => new PartnerHubAdmin);
        $this->app->singleton(ReferralAdminDashboard::class, fn () => new ReferralAdminDashboard);
    }

    /**
     * Bootstrap domain adapters and custom post types.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->app->make(PartnerPostType::class)->register();
        $this->app->make(LeadsAdminDashboard::class)->register();
        $this->app->make(WordPressAdminTheme::class)->register();
        $this->app->make(MarketingDashboard::class)->register();
        $this->app->make(ContentAuditAdmin::class)->register();
        $this->app->make(PartnerHubAdmin::class)->register();
        $this->app->make(ReferralAdminDashboard::class)->register();
    }
}
