<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Scheduling\Actions\RetryFailedBookingAction;
use App\Domains\Scheduling\Actions\WarmCalendlyMetadataCacheAction;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\CalendlyMetadataCache;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Scheduling\Gateways\GoogleCalendarClient;
use App\Domains\Scheduling\Listeners\HandleLeadCreatedForBooking;
use App\Domains\Scheduling\Services\CalendlyEventTypeDiscoveryService;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use App\Domains\Scheduling\Services\LiveCallAvailabilityRouter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SchedulingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CalendlyTokenPool::class, fn () => new CalendlyTokenPool);
        $this->app->singleton(CalendlyClient::class, fn ($app) => new CalendlyClient($app->make(CalendlyTokenPool::class)));
        $this->app->singleton(CalendlyMetadataCache::class);
        $this->app->singleton(CalendlyEventTypeDiscoveryService::class);
        $this->app->singleton(CalendlyEventTypeRoleResolver::class);
        $this->app->singleton(GoogleCalendarClient::class, fn () => new GoogleCalendarClient);
        $this->app->singleton(LiveCallAvailabilityRouter::class, fn () => new LiveCallAvailabilityRouter);
        $this->app->singleton(HandleLeadCreatedForBooking::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(LeadCreated::class, [HandleLeadCreatedForBooking::class, 'handle']);

        if (function_exists('add_action')) {
            add_action('init', function () {
                if (! wp_next_scheduled('rl_calendly_warm_cache_cron')) {
                    wp_schedule_event(time(), 'twicedaily', 'rl_calendly_warm_cache_cron');
                }
            });

            add_action('rl_calendly_warm_cache_cron', function () {
                app(WarmCalendlyMetadataCacheAction::class)->execute();
            });

            add_action('rl_calendly_refresh_questions', function (string $eventUri) {
                app(CalendlyMetadataCache::class)->warm($eventUri);
            }, 10, 1);

            add_action('rl_calendly_retry_booking', function (int $leadId) {
                app(RetryFailedBookingAction::class)->execute($leadId);
            }, 10, 1);
        }
    }
}
