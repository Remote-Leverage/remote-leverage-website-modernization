<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Actions;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Scheduling\Concerns\HandlesBookingRetryBackoff;
use App\Domains\Scheduling\Data\BookingRequestData;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use Illuminate\Support\Facades\Event;

class RetryFailedBookingAction
{
    use HandlesBookingRetryBackoff;

    public function __construct(
        protected BookMeetingAction $bookMeetingAction,
        protected LeadActivityLogger $activityLogger,
        protected CalendlyTokenPool $tokenPool,
    ) {}

    /**
     * Retry a previously failed booking attempt, reconstructing the exact request
     * from the Lead's most recent failed activity-log payload. Used by both the
     * WP-Cron backoff schedule and the admin "Retry Booking Now" action.
     */
    public function execute(int $leadId): array
    {
        $lead = Lead::findOrFail($leadId);

        $lastFailure = $lead->activityLogs()
            ->where('event_type', 'LeadCreated')
            ->where('outcome', 'failed')
            ->whereNotNull('payload')
            ->orderByDesc('created_at')
            ->first();

        if (! $lastFailure || empty($lastFailure->payload)) {
            return ['success' => false, 'message' => 'No reconstructable failed-booking payload found for this lead.'];
        }

        $payload = $lastFailure->payload;

        $bookingData = BookingRequestData::fromArray([
            'name' => $lead->name,
            'email' => $lead->email,
            'start_time' => $payload['preferred_slot'],
            'company' => $lead->company,
            'phone' => $lead->phone,
            'notes' => $lead->notes,
            'timezone' => $payload['timezone'] ?? 'America/New_York',
            'referral_code' => $payload['referral_code'] ?? $lead->source_id,
            'qualification_answers' => $payload['qualification_answers'] ?? null,
            'utm_source' => $payload['utm']['source'] ?? null,
            'utm_medium' => $payload['utm']['medium'] ?? null,
            'utm_campaign' => $payload['utm']['campaign'] ?? null,
            'utm_term' => $payload['utm']['term'] ?? null,
            'utm_content' => $payload['utm']['content'] ?? null,
            'guest_emails' => $payload['guest_emails'] ?? [],
        ]);

        $calendlyEventUri = $payload['calendly_event_uri'] ?? null;

        // Reset every token's metadata circuit breaker before retrying, mirroring
        // legacy's handle_retry_booking_cron behavior.
        $this->tokenPool->clearAllFailures('metadata');

        $result = $this->bookMeetingAction->execute($bookingData, $calendlyEventUri);

        if (! empty($result['success'])) {
            $lead->update(['status' => 'booked', 'booking_retry_count' => 0, 'booking_next_retry_at' => null]);

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadCreated',
                actorDomain: 'Scheduling',
                outcome: 'succeeded',
                description: "Scheduled consultation meeting on retry ({$result['provider']}: {$result['meeting_id']})",
                payload: [
                    'meeting_id' => $result['meeting_id'],
                    'provider' => $result['provider'],
                    'meet_url' => $result['meet_url'] ?? null,
                    'via' => 'retry',
                ]
            );

            Event::dispatch(new LeadBookingCompleted(
                lead: $lead,
                meetingId: (string) $result['meeting_id'],
                provider: (string) $result['provider'],
                meetUrl: $result['meet_url'] ?? null,
                startTime: $bookingData->startTime,
                metadata: $result
            ));

            return ['success' => true, 'result' => $result];
        }

        $this->recordBookingFailureAndMaybeReschedule(
            $lead,
            $bookingData,
            $calendlyEventUri,
            $result['message'] ?? 'Retry attempt failed',
            $this->activityLogger
        );

        return ['success' => false, 'message' => $result['message'] ?? 'Retry attempt failed'];
    }
}
