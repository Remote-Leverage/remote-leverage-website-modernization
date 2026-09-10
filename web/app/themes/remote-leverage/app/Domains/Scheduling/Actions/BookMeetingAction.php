<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Actions;

use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Scheduling\Data\BookingRequestData;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\CalendlyMetadataCache;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Scheduling\Gateways\GoogleCalendarClient;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BookMeetingAction
{
    public function __construct(
        protected CalendlyClient $calendlyClient,
        protected GoogleCalendarClient $googleCalendarClient,
        protected CalendlyTokenPool $tokenPool,
        protected CalendlyEventTypeRoleResolver $eventTypeRoleResolver,
        protected CalendlyMetadataCache $metadataCache,
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Book a strategy consultation meeting.
     * Supports Calendly invitee flow and Google Calendar fallback with Meet link generation.
     */
    public function execute(BookingRequestData $data, ?string $calendlyEventUri = null): array
    {
        $startTime = Carbon::parse($data->startTime, $data->timezone);
        $endTime = $startTime->copy()->addMinutes(30);

        Log::info('Executing BookMeetingAction', $data->toArray());

        // Duplicate-booking guard A: has this email already had a successful
        // booking logged for this EXACT slot within the last 5 minutes? Short-
        // circuits without touching Calendly/Google at all — but only for a
        // genuine repeat of the same slot. A different slot is treated as a
        // fresh (re)booking request below, not a duplicate.
        $existingSlotBooking = $this->activityLogger->findRecentBookingForSlot(
            $data->email,
            $startTime->toIso8601String()
        );

        if ($existingSlotBooking) {
            $payload = $existingSlotBooking->payload ?? [];

            return [
                'success' => true,
                'provider' => $payload['provider'] ?? 'calendly',
                'meeting_id' => $payload['meeting_id'] ?? 'deduplicated',
                'meet_url' => $payload['meet_url'] ?? null,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
                'client_name' => $data->name,
                'client_email' => $data->email,
                'idempotent_replay' => true,
            ];
        }

        // Path A: Calendly Direct Invitee Booking
        $eventUri = $calendlyEventUri ?: $this->eventTypeRoleResolver->get('default');

        if ($eventUri && ! empty($this->tokenPool->getEligibleTokens())) {
            // Duplicate-booking guard B: Calendly-side preflight — does this exact
            // slot already exist for this email on any pooled account?
            $existing = $this->calendlyClient->findExistingInvitee($data->email, $eventUri, $startTime->toIso8601String());

            if ($existing) {
                $meetUrl = null;
                if (! empty($existing['uri'])) {
                    $eventDetails = $this->calendlyClient->getScheduledEvent($existing['uri']);
                    $location = $eventDetails['location'] ?? [];
                    $meetUrl = $location['join_url'] ?? $location['location'] ?? null;
                }

                return [
                    'success' => true,
                    'provider' => 'calendly',
                    'meeting_id' => $existing['uri'] ?? uniqid('cal_dedup_', true),
                    'meet_url' => $meetUrl,
                    'start_time' => $startTime->toIso8601String(),
                    'end_time' => $endTime->toIso8601String(),
                    'client_name' => $data->name,
                    'client_email' => $data->email,
                    'idempotent_replay' => true,
                ];
            }
            $questionsAnswers = [];
            $eventQuestions = $this->metadataCache->getEventQuestions($eventUri);

            if (! empty($eventQuestions)) {
                foreach ($eventQuestions as $q) {
                    $qName = $q['name'] ?? '';
                    $pos = $q['position'] ?? 0;
                    $ans = null;

                    // Match against lead attributes
                    if (stripos($qName, 'phone') !== false || stripos($qName, 'cell') !== false || stripos($qName, 'whatsapp') !== false) {
                        $ans = $data->phone;
                    } elseif (stripos($qName, 'website') !== false || stripos($qName, 'company') !== false) {
                        $ans = $data->company ?: 'remoteleverage.com';
                    } elseif (stripos($qName, 'prepare') !== false || stripos($qName, 'help') !== false) {
                        $ans = $data->notes ?: 'VA Consultation';
                    } elseif (stripos($qName, 'role') !== false || stripos($qName, 'position') !== false) {
                        $ans = $data->qualificationAnswers['Role Needed'] ?? 'Virtual Assistant';
                    }

                    if (! empty($ans)) {
                        $questionsAnswers[] = [
                            'question' => $qName,
                            'answer' => (string) $ans,
                            'position' => (int) $pos,
                        ];
                    }
                }
            }

            $tracking = [
                'utm_source' => $data->utmSource ?: 'remoteleverage_site',
                'utm_medium' => $data->utmMedium,
                'utm_campaign' => $data->utmCampaign ?: ($data->referralCode ? 'ref_'.$data->referralCode : null),
                'utm_term' => $data->utmTerm,
                'utm_content' => $data->utmContent,
            ];

            $invitee = $this->calendlyClient->createInvitee(
                eventUri: $eventUri,
                email: $data->email,
                name: $data->name,
                startTime: $startTime->toIso8601String(),
                timezone: $data->timezone,
                phone: $data->phone,
                guestEmails: $data->guestEmails ?? [],
                questionsAnswers: $questionsAnswers,
                tracking: array_filter($tracking),
            );

            if ($invitee) {
                $scheduledEventUri = $invitee['event'] ?? null;
                $meetUrl = null;

                if ($scheduledEventUri) {
                    $eventDetails = $this->calendlyClient->getScheduledEvent($scheduledEventUri);
                    if ($eventDetails) {
                        $location = $eventDetails['location'] ?? [];
                        $meetUrl = $location['join_url'] ?? $location['location'] ?? null;
                    }
                }

                $meetUrl = $meetUrl ?: ($invitee['scheduling_url'] ?? 'https://meet.google.com/rl-strategy-'.substr(md5($data->email), 0, 8));

                $this->cancelPriorBookingForDifferentSlot($data->email, $startTime->toIso8601String());

                return [
                    'success' => true,
                    'provider' => 'calendly',
                    'meeting_id' => $invitee['uri'] ?? uniqid('cal_', true),
                    'meet_url' => $meetUrl,
                    'start_time' => $startTime->toIso8601String(),
                    'end_time' => $endTime->toIso8601String(),
                    'client_name' => $data->name,
                    'client_email' => $data->email,
                ];
            }
        }

        // Path B: Google Calendar Master Appointment Booking
        $summary = "Remote Leverage Strategy Call - {$data->name}";
        $description = "Lead Details:\n"
            ."Name: {$data->name}\n"
            ."Email: {$data->email}\n"
            .'Phone: '.($data->phone ?? 'N/A')."\n"
            .'Company: '.($data->company ?? 'N/A')."\n"
            .'Referral Code: '.($data->referralCode ?? 'Direct')."\n"
            .'Notes: '.($data->notes ?? 'None');

        $appointment = $this->googleCalendarClient->createAppointment(
            summary: $summary,
            startTime: $startTime->toRfc3339String(),
            endTime: $endTime->toRfc3339String(),
            attendees: [$data->email, (string) env('STRATEGY_CONSULTANT_EMAIL', 'team@remoteleverage.com')],
            description: $description,
        );

        $meetUrl = $appointment['conferenceData']['entryPoints'][0]['uri'] ?? 'https://meet.google.com/rl-consult';

        $this->cancelPriorBookingForDifferentSlot($data->email, $startTime->toIso8601String());

        return [
            'success' => true,
            'provider' => 'google_calendar',
            'meeting_id' => $appointment['id'] ?? uniqid('gcal_', true),
            'meet_url' => $meetUrl,
            'start_time' => $startTime->toIso8601String(),
            'end_time' => $endTime->toIso8601String(),
            'client_name' => $data->name,
            'client_email' => $data->email,
        ];
    }

    /**
     * Bonus reschedule support: if this email had a prior real Calendly
     * booking at a different slot, cancel it now that a new one has just been
     * made — otherwise a "changed my mind" resubmission would leave a stray
     * duplicate meeting on the calendar instead of moving it. Best-effort:
     * failure here is logged but never blocks the new booking from
     * succeeding, since the user's new meeting already exists either way.
     */
    protected function cancelPriorBookingForDifferentSlot(string $email, string $newStartTimeIso): void
    {
        $priorBooking = $this->activityLogger->findPriorBookingForDifferentSlot($email, $newStartTimeIso);

        if (! $priorBooking) {
            return;
        }

        $meetingId = $priorBooking->payload['meeting_id'] ?? null;

        if (! $meetingId) {
            return;
        }

        $cancelled = $this->calendlyClient->cancelScheduledEvent(
            $meetingId,
            'Rescheduled to a new time via the booking form.'
        );

        Log::{$cancelled ? 'info' : 'warning'}(
            'BookMeetingAction: '.($cancelled ? 'cancelled' : 'failed to cancel')
                ." prior Calendly booking {$meetingId} for {$email} after rebooking a different slot."
        );
    }
}
