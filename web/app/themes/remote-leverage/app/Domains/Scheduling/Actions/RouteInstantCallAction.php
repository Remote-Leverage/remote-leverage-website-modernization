<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Actions;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Models\LiveCallSession;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use App\Domains\Scheduling\Services\LiveCallAvailabilityRouter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class RouteInstantCallAction
{
    protected const PLACEHOLDER_URLS = ['https://meet.google.com', 'xds-kksk-qqt'];

    protected const IMMEDIATE_WINDOW_SECONDS = 900; // 15 minutes

    protected const CONCURRENCY_LOCK_WINDOW_MINUTES = 5;

    public function __construct(
        protected LiveCallAvailabilityRouter $router,
        protected CalendlyClient $calendlyClient,
        protected CalendlyEventTypeRoleResolver $eventTypeRoleResolver,
        protected PhoneValidationService $phoneValidator,
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Route a visitor to an instant live consultant call, backed by a real
     * Calendly booking. Ported from JoinLiveCallIntegration::process_booking_rest().
     */
    public function execute(array $visitorData, ?Lead $lead = null): array
    {
        $email = $visitorData['email'] ?? '';
        $phone = $visitorData['phone'] ?? '';
        $name = $visitorData['name'] ?? '';
        $sessionId = $visitorData['session_id'] ?? uniqid('session_', true);

        // 1. Idempotency: replay an already-booked session rather than re-booking.
        $existingSession = LiveCallSession::where('session_id', $sessionId)->where('status', 'booked')->first();
        if ($existingSession && ! $this->isPlaceholderUrl($existingSession->meeting_url)) {
            return [
                'routed' => true,
                'session_id' => $sessionId,
                'message' => 'Connecting to senior consultant...',
                'redirect_url' => $existingSession->meeting_url,
            ];
        }

        // 2. Phone validation: reuse PhoneValidationService, plus the Live-Call-
        // specific US/Canada-only restriction that service alone doesn't enforce.
        if (! empty($phone)) {
            $validated = $this->phoneValidator->validateAndFormat($phone, 'US');
            if (! $validated['isValid'] || ! in_array($validated['countryCode'], ['US', 'CA'], true)) {
                return [
                    'routed' => false,
                    'session_id' => $sessionId,
                    'message' => 'Live calls are currently only available for US and Canada phone numbers.',
                    'redirect_url' => null,
                ];
            }
        }

        // 3. Manual team-online toggle (independent of the concurrency lock below).
        $status = $this->router->getStatus();
        if (! $status['available']) {
            return [
                'routed' => false,
                'session_id' => $sessionId,
                'message' => 'Consultants are currently in sessions. Please schedule a time.',
                'redirect_url' => null,
            ];
        }

        // 4. Concurrency lock: is someone already on a call right now? Self-heal
        // if the "busy" session's invitee was actually canceled.
        $busy = LiveCallSession::where('status', 'booked')
            ->where('created_at', '>=', now()->subMinutes(self::CONCURRENCY_LOCK_WINDOW_MINUTES))
            ->first();

        if ($busy && $busy->calendly_invitee_uri) {
            $invitee = $this->calendlyClient->getInvitee($busy->calendly_invitee_uri);
            if (($invitee['status'] ?? null) === 'canceled') {
                $busy->update(['status' => 'canceled']);
                $busy = null;
            }
        }

        if ($busy) {
            return [
                'routed' => false,
                'session_id' => $sessionId,
                'message' => 'A sales representative is currently occupied in a live call.',
                'redirect_url' => null,
            ];
        }

        // 5. Search for an immediate (<=15 min out) slot.
        $eventTypeUri = $this->eventTypeRoleResolver->get('live_call');
        if (! $eventTypeUri) {
            return [
                'routed' => false,
                'session_id' => $sessionId,
                'message' => 'Live call event type is not configured.',
                'redirect_url' => null,
            ];
        }

        $slots = $this->calendlyClient->getAvailableSlots(
            $eventTypeUri,
            now()->addMinute()->toIso8601String(),
            now()->addHours(2)->toIso8601String()
        );

        $immediateSlot = null;
        foreach ($slots as $slot) {
            $secondsUntil = Carbon::parse($slot['start_time'])->diffInSeconds(now(), false) * -1;
            if ($secondsUntil >= 0 && $secondsUntil <= self::IMMEDIATE_WINDOW_SECONDS) {
                $immediateSlot = $slot;
                break;
            }
        }

        if (! $immediateSlot) {
            return [
                'routed' => false,
                'session_id' => $sessionId,
                'message' => 'No consultants have an immediate opening. Please schedule a time.',
                'redirect_url' => null,
            ];
        }

        // 6. Create the invitee.
        $session = LiveCallSession::create([
            'session_id' => $sessionId,
            'calendly_event_uri' => $eventTypeUri,
            'calendly_invitee_uri' => '',
            'meeting_url' => '',
            'status' => 'initiated',
            'visitor_email' => $email,
        ]);

        $invitee = $this->calendlyClient->createInvitee(
            eventUri: $eventTypeUri,
            email: $email,
            name: $name,
            startTime: $immediateSlot['start_time'],
            phone: $phone ?: null,
        );

        if (! $invitee) {
            $session->update(['status' => 'failed']);
            $this->activityLogger->logConsumption(
                leadId: $lead?->id ?? 0,
                eventType: 'InstantLiveCall',
                actorDomain: 'Scheduling',
                outcome: 'failed',
                description: 'Failed to create Calendly invitee for instant live call'
            );

            return [
                'routed' => false,
                'session_id' => $sessionId,
                'message' => 'Unable to connect right now. Please schedule a time.',
                'redirect_url' => null,
            ];
        }

        // 7. Poll for the Meet URL, falling back to the invitee's own link.
        $scheduledEventUri = $invitee['event'] ?? null;
        $meetUrl = $scheduledEventUri ? $this->calendlyClient->pollForMeetLocation($scheduledEventUri) : null;
        $meetUrl = $meetUrl ?: ($invitee['scheduling_url'] ?? null);

        if (empty($meetUrl) || $this->isPlaceholderUrl($meetUrl)) {
            $session->update(['status' => 'failed', 'calendly_invitee_uri' => $invitee['uri'] ?? '']);

            return [
                'routed' => false,
                'session_id' => $sessionId,
                'message' => 'Unable to resolve a meeting link. Please schedule a time.',
                'redirect_url' => null,
            ];
        }

        $session->update([
            'status' => 'booked',
            'meeting_url' => $meetUrl,
            'calendly_invitee_uri' => $invitee['uri'] ?? '',
        ]);

        $this->activityLogger->logConsumption(
            leadId: $lead?->id ?? 0,
            eventType: 'InstantLiveCall',
            actorDomain: 'Scheduling',
            outcome: 'succeeded',
            description: 'Instant live call booked via Calendly',
            payload: ['meeting_url' => $meetUrl, 'invitee_uri' => $invitee['uri'] ?? null]
        );

        // Reuses the exact same lifecycle event as the main booking wizard, so
        // Slack notification, referrer attribution, and KPI cache invalidation
        // all fire with zero Live-Call-specific code in those domains.
        if ($lead) {
            $lead->update(['status' => 'booked']);

            Event::dispatch(new LeadBookingCompleted(
                lead: $lead,
                meetingId: (string) ($invitee['uri'] ?? uniqid('live_', true)),
                provider: 'calendly',
                meetUrl: $meetUrl,
                startTime: $immediateSlot['start_time'],
                metadata: ['source' => 'instant_live_call']
            ));
        }

        Log::info('Instant live call booked', ['session_id' => $sessionId, 'meet_url' => $meetUrl]);

        return [
            'routed' => true,
            'session_id' => $sessionId,
            'message' => 'Connecting to senior consultant...',
            'redirect_url' => $meetUrl,
        ];
    }

    protected function isPlaceholderUrl(?string $url): bool
    {
        if (empty($url)) {
            return true;
        }

        foreach (self::PLACEHOLDER_URLS as $placeholder) {
            if (str_contains($url, $placeholder)) {
                return true;
            }
        }

        return $url === (string) env('LIVE_CALL_MEET_URL', '');
    }
}
