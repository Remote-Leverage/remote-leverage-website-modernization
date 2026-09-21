<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Actions;

use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Scheduling\Data\BookingRequestData;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\CalendlyMetadataCache;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BookMeetingAction
{
    public function __construct(
        protected CalendlyClient $calendlyClient,
        protected CalendlyTokenPool $tokenPool,
        protected CalendlyEventTypeRoleResolver $eventTypeRoleResolver,
        protected CalendlyMetadataCache $metadataCache,
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Book a strategy consultation meeting, at Calendly or not at all.
     *
     * There is one provider on purpose. See the note where the Google Calendar fallback used to
     * be, at the foot of this method.
     */
    public function execute(BookingRequestData $data, ?string $calendlyEventUri = null): array
    {
        $startTime = Carbon::parse($data->startTime, $data->timezone);
        $endTime = $startTime->copy()->addMinutes(30);

        Log::info('Executing BookMeetingAction', $data->toArray());

        // Duplicate-booking guard A: has this email already had a successful
        // booking logged for this EXACT slot within the last 5 minutes? Short-
        // circuits without touching Calendly at all — but only for a
        // genuine repeat of the same slot. A different slot is treated as a
        // fresh (re)booking request below, not a duplicate.
        $existingSlotBooking = $this->activityLogger->findRecentBookingForSlot(
            $data->email,
            $startTime->toIso8601String()
        );

        if ($existingSlotBooking) {
            $payload = $existingSlotBooking->payload ?? [];

            /*
             * Replay the *prior* booking only if it names a real meeting. A log row with no
             * meeting id is the record of a booking that never reached a provider, and echoing
             * it back as a success turns one phantom booking into every subsequent retry's
             * answer — the failure becomes sticky and self-confirming.
             */
            if (empty($payload['meeting_id'])) {
                Log::warning('BookMeetingAction: recent booking log for this slot names no meeting, not replaying it', [
                    'email' => $data->email,
                    'start_time' => $startTime->toIso8601String(),
                    'log_id' => $existingSlotBooking->id ?? null,
                ]);

                return [
                    'success' => false,
                    'error_code' => 'provider_unavailable',
                    'message' => 'The previous attempt at this time never reached the calendar.',
                ];
            }

            return [
                'success' => true,
                'provider' => $payload['provider'] ?? 'calendly',
                'meeting_id' => $payload['meeting_id'],
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

            /*
             * The preflight found this slot already taken by this email but could not name the
             * invitee. Falling through would book a second one; returning a fabricated id would
             * confirm a meeting nobody can look up. Neither is acceptable, so this reports a
             * failure and the retry ladder asks Calendly again.
             */
            if ($existing && empty($existing['uri'])) {
                Log::warning('BookMeetingAction: Calendly reported an existing invitee with no uri', [
                    'email' => $data->email,
                    'start_time' => $startTime->toIso8601String(),
                    'event_uri' => $eventUri,
                ]);

                return [
                    'success' => false,
                    'error_code' => 'provider_unavailable',
                    'message' => 'Calendly reported an existing booking at this time but could not identify it.',
                ];
            }

            if ($existing) {
                $eventDetails = $this->calendlyClient->getScheduledEvent($existing['uri']);
                $location = $eventDetails['location'] ?? [];
                $meetUrl = $location['join_url'] ?? $location['location'] ?? null;

                return [
                    'success' => true,
                    'provider' => 'calendly',
                    'meeting_id' => $existing['uri'],
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
                        // No fallback on purpose. This used to answer 'remoteleverage.com' when
                        // the lead had no company — and the booking wizard never collects one, so
                        // it fired on essentially every booking. Calendly's HubSpot integration
                        // writes the answer onto the contact, which is how the CRM filled up with
                        // our own domain in the Company column. An unanswered question leaves
                        // whatever HubSpot already holds intact; a wrong answer overwrites it.
                        $ans = $data->company;
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

            /*
             * Calendly answered, but with nothing that identifies the invitee it claims to have
             * created. There is no meeting anybody can look up, cancel or reschedule, so this is
             * a failure however encouraging the response looked.
             */
            if ($invitee && empty($invitee['uri'])) {
                Log::warning('BookMeetingAction: Calendly returned an invitee with no uri', [
                    'email' => $data->email,
                    'start_time' => $startTime->toIso8601String(),
                    'event_uri' => $eventUri,
                ]);

                return [
                    'success' => false,
                    'error_code' => 'provider_unavailable',
                    'message' => 'Calendly accepted the booking but did not return a meeting we can confirm.',
                ];
            }

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

                /*
                 * No invented link. This used to fall back to a meet.google.com URL built from
                 * a hash of the email, which is a room that has never existed — see the same
                 * mistake, with the same consequences, in Path B below. Null renders as "no
                 * link yet" and the invitee still gets Calendly's own confirmation email.
                 */
                $meetUrl = $meetUrl ?: ($invitee['scheduling_url'] ?? null);

                $this->cancelPriorBookingForDifferentSlot($data->email, $startTime->toIso8601String());

                return [
                    'success' => true,
                    'provider' => 'calendly',
                    'meeting_id' => $invitee['uri'],
                    'meet_url' => $meetUrl,
                    'start_time' => $startTime->toIso8601String(),
                    'end_time' => $endTime->toIso8601String(),
                    'client_name' => $data->name,
                    'client_email' => $data->email,
                ];
            }

            /*
             * Calendly *refused* the booking rather than failing to answer, and the two want
             * opposite handling. Falling through to Google is defensible when Calendly is
             * unreachable; it is indefensible for a refusal, and actively harmful for the
             * common one.
             *
             * `already_filled` means somebody else took the slot between this visitor rendering
             * the picker and submitting it — which is exactly what happened on 2026-09-20.
             * Path B would then put a meeting on the consultant's calendar at a time they are
             * already booked, and hand the visitor a confirmation for it.
             */
            $refusal = $this->calendlyClient->lastInviteeErrorCode();

            if ($refusal !== null) {
                Log::warning("BookMeetingAction: Calendly refused the booking ({$refusal}), not falling back to Google", [
                    'email' => $data->email,
                    'start_time' => $startTime->toIso8601String(),
                    'event_uri' => $eventUri,
                ]);

                return [
                    'success' => false,
                    'error_code' => $refusal === 'already_filled' ? 'slot_taken' : 'calendly_rejected',
                    'message' => $refusal === 'already_filled'
                        ? 'That time was taken by someone else before the booking went through.'
                        : "Calendly rejected the booking request ({$refusal}).",
                ];
            }
        }

        /*
         * There is no Path B any more.
         *
         * A Google Calendar fallback sat here from the beginning, on the reasonable-sounding
         * theory that a booking is too valuable to lose to one provider being unreachable. The
         * log says it never once worked: across every booking this application has ever taken,
         * Calendly produced 241 meetings carrying a real invitee uri and Google produced five,
         * every one of them a `uniqid()` this code minted because `createAppointment()` had
         * returned null. Not one real Google event exists. The fallback's entire measurable
         * output is five people told a consultant was expecting them.
         *
         * So it was not a fallback, it was a way of converting an outage into a silent lie. A
         * failure that says so puts the lead on the retry ladder — five attempts over 32 minutes,
         * which is a real second chance at the same slot — and tells the visitor the truth
         * meanwhile. That is strictly more booking than the path it replaces.
         */
        Log::warning('BookMeetingAction: Calendly could not take the booking', [
            'email' => $data->email,
            'start_time' => $startTime->toIso8601String(),
            'event_uri' => $eventUri ?? null,
            'reason' => ($eventUri && ! empty($this->tokenPool->getEligibleTokens()))
                ? 'calendly_unreachable'
                : 'calendly_not_configured',
        ]);

        return [
            'success' => false,
            'error_code' => 'provider_unavailable',
            'message' => 'The calendar could not take the booking just now.',
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
