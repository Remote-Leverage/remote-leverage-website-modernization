<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\GoogleCalendarClient;
use App\Domains\Scheduling\Services\LiveCallAvailabilityRouter;
use Illuminate\Support\ServiceProvider;

class SchedulingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CalendlyClient::class, fn () => new CalendlyClient);
        $this->app->singleton(GoogleCalendarClient::class, fn () => new GoogleCalendarClient);
        $this->app->singleton(LiveCallAvailabilityRouter::class, fn () => new LiveCallAvailabilityRouter);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Boot scheduling-related features or bindings
    }
}
