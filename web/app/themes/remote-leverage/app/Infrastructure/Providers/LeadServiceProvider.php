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
        // Steps 1-3b below are all "fire and forget" notification/CRM side effects
        // that nothing in the request depends on — none of their return values
        // are read by the booking wizard. Running them synchronously (with no
        // queue configured — config('queue.default') is 'sync' — every one of
        // these blocks the Livewire response) is what made both the step-1
        // partial-capture submission and the final booking submission feel
        // slow: up to five sequential external calls (HubSpot, Slack, webhook,
        // email, Customer.io/PostHog) before the user sees anything happen.
        // dispatch(...)->afterResponse() defers each to run after the response
        // has already been sent to the browser (Acorn's request-handling hooks
        // into WordPress's `shutdown` action specifically to flush the response
        // via fastcgi_finish_request() before calling the container's
        // terminating() callbacks, which is what this relies on).

        // 1. Automatically sync to HubSpot on LeadCreated
        //
        // The inner closure passed to dispatch() MUST be `static` and MUST NOT
        // reference $this or $this->app: dispatch()'s job-preparation pipeline
        // always runs the closure through laravel/serializable-closure (even
        // for ->afterResponse(), since the same code path is shared with real
        // queue connections), and a non-static closure captures $this — here,
        // the entire ServiceProvider bound to the whole Application/container
        // graph — which serializable-closure then tries to serialize. That
        // blew the memory limit and fatally crashed silently during request
        // termination, so the closure never ran at all and every one of these
        // side effects was just dropped with no error visible to the request.
        // The global app() helper (not $this->app) avoids the capture entirely.
        Event::listen(LeadCreated::class, function (LeadCreated $event) {
            dispatch(static function () use ($event) {
                $gateway = app(HubSpotGateway::class);
                $logger = app(LeadActivityLogger::class);

                $contactId = $gateway->syncContact($event->lead);

                $logger->logConsumption(
                    leadId: $event->lead->id,
                    eventType: 'LeadCreated',
                    actorDomain: 'Lead',
                    outcome: $contactId ? 'succeeded' : 'failed',
                    description: $contactId ? "Synced contact to HubSpot (ID: {$contactId})" : 'HubSpot contact sync failed',
                    payload: ['hubspot_contact_id' => $contactId]
                );
            })->afterResponse();
        });

        // 2. Dispatch Slack notification on LeadCreated (partial) and LeadBookingCompleted (final)
        Event::listen(LeadCreated::class, function (LeadCreated $event) {
            dispatch(static fn () => app(HandleLeadEventsForSlack::class)->handleCreated($event))->afterResponse();
        });
        Event::listen(LeadBookingCompleted::class, function (LeadBookingCompleted $event) {
            dispatch(static fn () => app(HandleLeadEventsForSlack::class)->handleBookingCompleted($event))->afterResponse();
        });

        // 3. Dispatch Outgoing Webhook on LeadCreated (partial) and LeadBookingCompleted (final)
        Event::listen(LeadCreated::class, function (LeadCreated $event) {
            dispatch(static fn () => app(HandleLeadEventsForWebhook::class)->handleCreated($event))->afterResponse();
        });
        Event::listen(LeadBookingCompleted::class, function (LeadBookingCompleted $event) {
            dispatch(static fn () => app(HandleLeadEventsForWebhook::class)->handleBookingCompleted($event))->afterResponse();
        });

        // 3b. Notify admin-configured recipient emails on LeadCreated (WR-102)
        Event::listen(LeadCreated::class, function (LeadCreated $event) {
            dispatch(static fn () => app(HandleLeadEventsForEmailNotification::class)->handleCreated($event))->afterResponse();
        });

        // 4. Attribute completed bookings to referrers — kept synchronous
        // (unlike 1-3b above): this is reward/payout bookkeeping rather than a
        // notification, it's DB-only with no external calls so it's not what
        // was making anything feel slow, and guaranteed execution matters
        // more here than shaving off latency the user wouldn't even notice.
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
