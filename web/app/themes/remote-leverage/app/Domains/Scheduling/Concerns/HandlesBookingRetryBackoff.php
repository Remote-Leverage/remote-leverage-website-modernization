<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Concerns;

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Scheduling\Data\BookingRequestData;

/**
 * Shared failure/backoff logic for a failed booking attempt, used by both
 * HandleLeadCreatedForBooking (the initial attempt) and RetryFailedBookingAction
 * (subsequent attempts), so the schedule/exhaustion behavior lives in one place.
 *
 * Backoff schedule ported from legacy: max 5 retries, delays 30s, 4m, 8m, 16m, 32m.
 */
trait HandlesBookingRetryBackoff
{
    protected const MAX_RETRIES = 5;

    /** @var array<int, int> */
    protected const BACKOFF_SECONDS = [1 => 30, 2 => 240, 3 => 480, 4 => 960, 5 => 1920];

    protected function recordBookingFailureAndMaybeReschedule(
        Lead $lead,
        BookingRequestData $bookingData,
        ?string $calendlyEventUri,
        string $reason,
        LeadActivityLogger $activityLogger
    ): void {
        $retryCount = $lead->booking_retry_count + 1;
        $exhausted = $retryCount > self::MAX_RETRIES;

        $payload = [
            'preferred_slot' => $bookingData->startTime,
            'timezone' => $bookingData->timezone,
            'calendly_event_uri' => $calendlyEventUri,
            'guest_emails' => $bookingData->guestEmails,
            'qualification_answers' => $bookingData->qualificationAnswers,
            'utm' => [
                'source' => $bookingData->utmSource,
                'medium' => $bookingData->utmMedium,
                'campaign' => $bookingData->utmCampaign,
                'term' => $bookingData->utmTerm,
                'content' => $bookingData->utmContent,
            ],
            'referral_code' => $bookingData->referralCode,
            'retry_count' => $retryCount,
            'reason' => $reason,
        ];

        $activityLogger->logConsumption(
            leadId: $lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'Scheduling',
            outcome: 'failed',
            description: $exhausted
                ? "Booking failed permanently after {$retryCount} attempts: {$reason}"
                : "Booking attempt #{$retryCount} failed, retry scheduled: {$reason}",
            payload: $payload
        );

        if ($exhausted) {
            $lead->update([
                'status' => 'booking_failed',
                'booking_retry_count' => $retryCount,
                'booking_next_retry_at' => null,
            ]);

            return;
        }

        $delaySeconds = self::BACKOFF_SECONDS[$retryCount];

        $lead->update([
            'booking_retry_count' => $retryCount,
            'booking_next_retry_at' => now()->addSeconds($delaySeconds),
        ]);

        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time() + $delaySeconds, 'rl_calendly_retry_booking', [$lead->id]);
        }
    }
}
