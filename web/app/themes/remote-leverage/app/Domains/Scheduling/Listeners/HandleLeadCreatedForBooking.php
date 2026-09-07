<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Scheduling\Actions\BookMeetingAction;
use App\Domains\Scheduling\Data\BookingRequestData;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class HandleLeadCreatedForBooking
{
    public function __construct(
        protected BookMeetingAction $bookMeetingAction,
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle lead created event: schedule meeting if slot was selected.
     */
    public function handle(LeadCreated $event): void
    {
        $lead = $event->lead;
        $preferredSlot = $event->context['preferred_slot'] ?? null;
        $timezone = $event->context['timezone'] ?? 'America/New_York';

        if (! $preferredSlot) {
            // Lead captured without an instant booking slot
            return;
        }

        try {
            $bookingData = BookingRequestData::fromArray([
                'name' => $lead->name,
                'email' => $lead->email,
                'start_time' => $preferredSlot,
                'company' => $lead->company,
                'phone' => $lead->phone,
                'notes' => $lead->notes,
                'timezone' => $timezone,
                'referral_code' => $lead->source_id,
                'qualification_answers' => [
                    'Role Needed' => $lead->role_needed,
                    'Weekly Hours' => $lead->weekly_hours,
                    'Start Timeline' => $lead->start_date,
                ],
            ]);

            $result = $this->bookMeetingAction->execute($bookingData);

            if (! empty($result['success'])) {
                // Update lead status
                $lead->update(['status' => 'booked']);

                // Dual-logging Stage 2: consumption write
                $this->activityLogger->logConsumption(
                    leadId: $lead->id,
                    eventType: 'LeadCreated',
                    actorDomain: 'Scheduling',
                    outcome: 'succeeded',
                    description: "Scheduled consultation meeting ({$result['provider']}: {$result['meeting_id']})",
                    payload: [
                        'meeting_id' => $result['meeting_id'],
                        'provider' => $result['provider'],
                        'meet_url' => $result['meet_url'] ?? null,
                    ]
                );

                // Dispatch LeadBookingCompleted lifecycle event
                Event::dispatch(new LeadBookingCompleted(
                    lead: $lead,
                    meetingId: (string) $result['meeting_id'],
                    provider: (string) $result['provider'],
                    meetUrl: $result['meet_url'] ?? null,
                    startTime: $preferredSlot,
                    metadata: $result
                ));
            } else {
                $this->activityLogger->logConsumption(
                    leadId: $lead->id,
                    eventType: 'LeadCreated',
                    actorDomain: 'Scheduling',
                    outcome: 'failed',
                    description: 'Failed to book slot with calendar provider'
                );
            }
        } catch (\Throwable $e) {
            Log::error("HandleLeadCreatedForBooking: Error booking meeting for lead #{$lead->id}: ".$e->getMessage());

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadCreated',
                actorDomain: 'Scheduling',
                outcome: 'failed',
                description: 'Scheduling exception: '.$e->getMessage()
            );
        }
    }
}
