<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Scheduling\Actions\BookMeetingAction;
use App\Domains\Scheduling\Concerns\HandlesBookingRetryBackoff;
use App\Domains\Scheduling\Data\BookingRequestData;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class HandleLeadCreatedForBooking
{
    use HandlesBookingRetryBackoff;

    public function __construct(
        protected BookMeetingAction $bookMeetingAction,
        protected LeadActivityLogger $activityLogger,
        protected CalendlyEventTypeRoleResolver $eventTypeRoleResolver,
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
                    'Monthly Revenue' => $lead->monthly_revenue,
                ],
                'utm_source' => $lead->utm_source,
                'utm_medium' => $lead->utm_medium,
                'utm_campaign' => $lead->utm_campaign,
                'utm_term' => $lead->utm_term,
                'utm_content' => $lead->utm_content,
                'guest_emails' => $event->context['extra_data']['guest_emails'] ?? [],
            ]);

            $extraData = $event->context['extra_data'] ?? [];
            $calendlyEventUri = $extraData['event_uri'] ?? null;
            if (! $calendlyEventUri && $lead->monthly_revenue) {
                $isUnder10k = in_array($lead->monthly_revenue, ['$0 to $5k Per Month', '$5k to $10k Per Month', '<10k', 'under_10k'], true);
                $calendlyEventUri = $isUnder10k
                    ? $this->eventTypeRoleResolver->get('t0')
                    : $this->eventTypeRoleResolver->get('t10');
            }

            $result = $this->bookMeetingAction->execute($bookingData, $calendlyEventUri);

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
                        'start_time' => $result['start_time'] ?? $preferredSlot,
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
                $this->recordBookingFailureAndMaybeReschedule(
                    $lead,
                    $bookingData,
                    $calendlyEventUri,
                    $result['message'] ?? 'Failed to book slot with calendar provider',
                    $this->activityLogger
                );
            }
        } catch (\Throwable $e) {
            Log::error("HandleLeadCreatedForBooking: Error booking meeting for lead #{$lead->id}: ".$e->getMessage());

            $this->recordBookingFailureAndMaybeReschedule(
                $lead,
                $bookingData ?? BookingRequestData::fromArray(['name' => $lead->name, 'email' => $lead->email, 'start_time' => $preferredSlot, 'timezone' => $timezone]),
                $calendlyEventUri ?? null,
                'Scheduling exception: '.$e->getMessage(),
                $this->activityLogger
            );
        }
    }
}
