<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Actions\PurgeOldLeadsAction;
use App\Domains\Lead\Commands\PurgeLeadsCommand;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Services\HubSpotGateway;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\PartnerHub\Listeners\HandleLeadBookingCompletedForPartner;
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
        $this->app->singleton(HubSpotGateway::class, fn () => new HubSpotGateway);
        $this->app->singleton(LeadActivityLogger::class, fn () => new LeadActivityLogger);
        $this->app->singleton(CaptureLeadAction::class);
        $this->app->singleton(PurgeOldLeadsAction::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgeLeadsCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any Lead domain events.
     */
    public function boot(): void
    {
        // Automatically sync to HubSpot on LeadCreated
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

        // Attribute completed bookings to partners in PartnerHub
        Event::listen(
            LeadBookingCompleted::class,
            [HandleLeadBookingCompletedForPartner::class, 'handle']
        );
    }
}
