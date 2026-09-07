<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

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
        $this->app->singleton(\App\Infrastructure\WordPress\Admin\LeadsAdminDashboard::class, fn () => new \App\Infrastructure\WordPress\Admin\LeadsAdminDashboard);
    }

    /**
     * Bootstrap domain adapters and custom post types.
     */
    public function boot(): void
    {
        $this->app->make(PartnerPostType::class)->register();
        $this->app->make(\App\Infrastructure\WordPress\Admin\LeadsAdminDashboard::class)->register();
    }
}
