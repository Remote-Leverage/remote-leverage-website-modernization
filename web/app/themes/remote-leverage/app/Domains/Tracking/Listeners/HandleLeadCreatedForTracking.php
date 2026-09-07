<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Listeners;

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use App\Domains\Tracking\Data\UserProfileData;
use App\Domains\Tracking\Gateways\CustomerIOClient;
use Illuminate\Support\Facades\Log;

class HandleLeadCreatedForTracking
{
    public function __construct(
        protected CustomerIOClient $customerIO,
        protected RecordBehaviorEventAction $recordEventAction,
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle the LeadCreated event for telemetry & Customer.io identification.
     */
    public function handle(LeadCreated $event): void
    {
        $lead = $event->lead;

        try {
            // 1. Identify contact in Customer.io
            $profile = UserProfileData::fromArray([
                'identifier' => $lead->email,
                'email' => $lead->email,
                'name' => $lead->name,
                'referral_code' => $lead->source_id,
                'traits' => [
                    'source_type' => $lead->source_type,
                    'source_id' => $lead->source_id,
                    'role_needed' => $lead->role_needed,
                    'weekly_hours' => $lead->weekly_hours,
                    'lead_id' => $lead->id,
                    'status' => $lead->status,
                ],
            ]);

            $this->customerIO->identify($profile);

            // 2. Record telemetry event in PostHog
            $analyticsEvent = AnalyticsEventData::fromArray([
                'event' => 'Lead Captured',
                'distinct_id' => $lead->email,
                'properties' => [
                    'lead_id' => $lead->id,
                    'email' => $lead->email,
                    'source_type' => $lead->source_type,
                    'source_id' => $lead->source_id,
                    'role_needed' => $lead->role_needed,
                ],
            ]);

            $this->recordEventAction->execute($analyticsEvent);

            // 3. Dual-Logging: Stage 2 (Consumption write)
            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadCreated',
                actorDomain: 'Tracking',
                outcome: 'succeeded',
                description: 'Identified user in Customer.io and recorded PostHog telemetry event',
                payload: ['distinct_id' => $lead->email]
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadCreatedForTracking: Error processing lead #{$lead->id}: ".$e->getMessage());

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadCreated',
                actorDomain: 'Tracking',
                outcome: 'failed',
                description: 'Failed to record tracking: '.$e->getMessage()
            );
        }
    }
}
