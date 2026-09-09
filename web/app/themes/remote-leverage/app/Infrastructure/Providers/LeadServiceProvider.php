<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Actions\ProcessAbandonedLeadsAction;
use App\Domains\Lead\Actions\PurgeOldLeadsAction;
use App\Domains\Lead\Commands\ProcessAbandonedLeadsCommand;
use App\Domains\Lead\Commands\PurgeLeadsCommand;
use App\Domains\Lead\Events\LeadBookingCanceled;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Listeners\HandleLeadEventsForEmailNotification;
use App\Domains\Lead\Listeners\HandleLeadEventsForSlack;
use App\Domains\Lead\Listeners\HandleLeadEventsForWebhook;
use App\Domains\Lead\Services\HubSpotGateway;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadSettingsService;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Listeners\HandleLeadBookingCanceledForReferrer;
use App\Domains\Referral\Listeners\HandleLeadBookingCompletedForReferrer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LeadServiceProvider extends ServiceProvider
{
    /**
     * Register any Lead domain services.
     */
    public function register(): void
    {
        $this->app->singleton(PhoneValidationService::class, fn () => new PhoneValidationService);
        $this->app->singleton(LeadSettingsService::class, fn () => new LeadSettingsService);
        $this->app->singleton(HubSpotGateway::class);
        $this->app->singleton(LeadActivityLogger::class, fn () => new LeadActivityLogger);
        $this->app->singleton(HandleLeadEventsForSlack::class);
        $this->app->singleton(HandleLeadEventsForWebhook::class);
        $this->app->singleton(HandleLeadEventsForEmailNotification::class);
        $this->app->singleton(CaptureLeadAction::class);
        $this->app->singleton(PurgeOldLeadsAction::class);
        $this->app->singleton(ProcessAbandonedLeadsAction::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgeLeadsCommand::class,
                ProcessAbandonedLeadsCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any Lead domain events.
     */
    public function boot(): void
    {
        // 1. Automatically sync to HubSpot on LeadCreated
        Event::listen(LeadCreated::class, function (LeadCreated $event) {
            $gateway = $this->app->make(HubSpotGateway::class);
            $logger = $this->app->make(LeadActivityLogger::class);

            $contactId = $gateway->syncContact($event->lead);

            $logger->logConsumption(
                leadId: $event->lead->id,
                eventType: 'LeadCreated',
                actorDomain: 'Lead',
                outcome: $contactId ? 'succeeded' : 'failed',
                description: $contactId ? "Synced contact to HubSpot (ID: {$contactId})" : 'HubSpot contact sync failed',
                payload: ['hubspot_contact_id' => $contactId]
            );
        });

        // 2. Dispatch Slack notification on LeadCreated (partial) and LeadBookingCompleted (final)
        Event::listen(LeadCreated::class, [HandleLeadEventsForSlack::class, 'handleCreated']);
        Event::listen(LeadBookingCompleted::class, [HandleLeadEventsForSlack::class, 'handleBookingCompleted']);

        // 3. Dispatch Outgoing Webhook on LeadCreated (partial) and LeadBookingCompleted (final)
        Event::listen(LeadCreated::class, [HandleLeadEventsForWebhook::class, 'handleCreated']);
        Event::listen(LeadBookingCompleted::class, [HandleLeadEventsForWebhook::class, 'handleBookingCompleted']);

        // 3b. Notify admin-configured recipient emails on LeadCreated (WR-102)
        Event::listen(LeadCreated::class, [HandleLeadEventsForEmailNotification::class, 'handleCreated']);

        // 4. Attribute completed bookings to referrers
        Event::listen(
            LeadBookingCompleted::class,
            [HandleLeadBookingCompletedForReferrer::class, 'handle']
        );

        // 4b. Reverse referrer attribution (due reward + referral status) if the booking is later canceled
        Event::listen(
            LeadBookingCanceled::class,
            [HandleLeadBookingCanceledForReferrer::class, 'handle']
        );

        // 5. Invalidate admin dashboard KPI cache on lead lifecycle events
        Event::listen([LeadCreated::class, LeadBookingCompleted::class], function () {
            Cache::forget('rl_lead_dashboard_kpi_metrics');
        });

        // 6. Hourly WP-Cron: process leads abandoned before completing booking (ADR-0008)
        if (function_exists('add_action')) {
            add_action('init', function () {
                if (! wp_next_scheduled('rl_process_abandoned_leads')) {
                    wp_schedule_event(time(), 'hourly', 'rl_process_abandoned_leads');
                }
            });

            add_action('rl_process_abandoned_leads', function () {
                $this->app->make(ProcessAbandonedLeadsAction::class)->execute();
            });
        }
    }
}
