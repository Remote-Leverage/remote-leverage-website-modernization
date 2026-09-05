<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Tracking\Gateways\CustomerIOClient;
use App\Domains\Tracking\Gateways\PostHogClient;
use App\Domains\Tracking\Subscribers\GravityFormsSubmissionSubscriber;
use App\Infrastructure\WordPress\Hooks\GravityFormsHooks;
use App\Infrastructure\WordPress\Hooks\TrackingHooks;
use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CustomerIOClient::class, fn () => new CustomerIOClient);
        $this->app->singleton(PostHogClient::class, fn () => new PostHogClient);
        $this->app->singleton(TrackingHooks::class, fn () => new TrackingHooks);
        $this->app->singleton(GravityFormsHooks::class, fn ($app) => new GravityFormsHooks(
            $app->make(GravityFormsSubmissionSubscriber::class)
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(TrackingHooks::class)->register();
        $this->app->make(GravityFormsHooks::class)->register();
    }
}
