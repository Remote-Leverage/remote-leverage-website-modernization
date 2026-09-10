<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Gateways;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendlyClient
{
    protected CalendlyTokenPool $tokenPool;

    protected ?string $userUri;

    public function __construct(?CalendlyTokenPool $tokenPool = null)
    {
        $this->tokenPool = $tokenPool ?: new CalendlyTokenPool;
        $this->userUri = config('services.calendly.user_uri');
    }

    /**
     * Send a Calendly request, iterating the token pool's eligible tokens in order.
     * $context is passed through to CalendlyTokenPool::getEligibleTokens() — pass
     * 'metadata' for circuit-breaker-gated calls (only getEventQuestions() does this
     * today), leave null for the real booking/availability paths, matching legacy.
     *
     * Per-status handling (verified against the legacy plugin):
     * - 429: mark rate-limited, rotate to the next token.
     * - 401/403: record a failure for this token/context, rotate.
     * - 404: rotate WITHOUT recording a failure (legacy: "this token's account
     *   doesn't own this event," not a broken token).
     * - connection exception, or 5xx: HALT immediately (legacy schedules a
     *   background retry here instead of rotating through the rest of the pool).
     * - anything else (2xx, or an actionable 4xx like 400/422): return immediately.
     *
     * Return contract for callers: null or a 5xx response means a transient outage
     * (retry later); a response with status in {401,403,404,429} means the pool was
     * exhausted (not a "Calendly is down" signal); anything else is a real answer.
     */
    protected function sendWithFailover(callable $makeRequest, ?string $context = null): ?Response
    {
        $tokens = $this->tokenPool->getEligibleTokens($context);
        if (empty($tokens)) {
            return null;
        }

        $lastResponse = null;

        foreach ($tokens as $item) {
            $token = $item['token'];

            try {
                $response = $makeRequest($token);
            } catch (\Throwable $e) {
                Log::error("CalendlyClient: connection error on token [{$item['label']}]: {$e->getMessage()}");

                return null;
            }

            $status = $response->status();

            if ($status === 429) {
                Log::warning("CalendlyClient: token [{$item['label']}] rate-limited, failing over to next token in pool");
                $this->tokenPool->markRateLimited($token);
                $lastResponse = $response;

                continue;
            }

            if ($status === 401 || $status === 403) {
                Log::warning("CalendlyClient: token [{$item['label']}] returned {$status}, recording failure and failing over");
                $this->tokenPool->recordFailure($token, $context ?? 'booking');
                $lastResponse = $response;

                continue;
            }

            if ($status === 404) {
                Log::warning("CalendlyClient: token [{$item['label']}] returned 404, may belong to a different account, failing over");
                $lastResponse = $response;

                continue;
            }

            if ($status >= 500) {
                Log::error("CalendlyClient: token [{$item['label']}] returned server error {$status}, halting pool iteration");

                return $response;
            }

            return $response;
        }

        return $lastResponse;
    }

    /**
     * Send a request using one specific token, with no failover/rotation. Used by
     * flows that must address every pooled token explicitly (e.g. event-type
     * discovery across accounts) rather than trying one and failing over.
     */
    public function getForToken(string $token, string $url, array $query = []): ?Response
    {
        try {
            // Guzzle/Laravel's HTTP client treats an explicit `query` option — even
            // an empty array — as a full replacement of the URL's existing query
            // string. Calendly's `pagination.next_page` URLs already carry their
            // own query string (organization/user/page_token), so passing []
            // here would silently strip it and break every page after the first.
            // Only pass $query through when there's something to add.
            return empty($query)
                ? Http::withToken($token)->timeout(15)->get($url)
                : Http::withToken($token)->timeout(15)->get($url, $query);
        } catch (\Throwable $e) {
            Log::error("CalendlyClient::getForToken exception: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Fetch event type availability slots from Calendly.
     * Ported from CalendlyIntegration::get_availability().
     */
    public function getAvailableSlots(string $eventTypeId, string $startTime, string $endTime, ?string $timezone = null): array
    {
        $params = [
            'event_type' => $eventTypeId,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];

        if ($timezone) {
            $params['timezone'] = $timezone;
        }

        try {
            $response = $this->sendWithFailover(
                fn (string $token) => Http::withToken($token)
                    ->timeout(15)
                    ->get('https://api.calendly.com/event_type_available_times', $params)
            );

            if (! $response) {
                Log::warning('CalendlyClient: No eligible tokens in pool (none configured, or all rate-limited/circuit-broken)');

                return [];
            }

            if ($response->failed()) {
                Log::error('CalendlyClient: Error fetching slots', $response->json() ?? ['body' => $response->body()]);

                return [];
            }

            return $response->json('collection', []);
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Exception: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Fetch event type details from Calendly.
     */
    public function getEventType(string $eventUriOrUuid, ?string $context = null): ?array
    {
        $url = str_starts_with($eventUriOrUuid, 'http')
            ? $eventUriOrUuid
            : 'https://api.calendly.com/event_types/'.rawurlencode($eventUriOrUuid);

        try {
            $response = $this->sendWithFailover(fn (string $token) => Http::withToken($token)->get($url), $context);

            return $response && $response->successful() ? $response->json('resource') : null;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Event Type Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Fetch custom questions for an event type. This is the one legacy call site that
     * used the 'metadata' circuit-breaker context (get_booking_questions), so it's
     * hardcoded here rather than exposed as a caller-supplied parameter.
     */
    public function getEventQuestions(string $eventUriOrUuid): array
    {
        $eventType = $this->getEventType($eventUriOrUuid, 'metadata');

        return $eventType['custom_questions'] ?? [];
    }

    /**
     * Book appointment by creating invitee on Calendly.
     * Ported from CalendlyIntegration::process_calendly_booking() / api.calendly.com/invitees.
     */
    public function createInvitee(
        string $eventUri,
        string $email,
        string $name,
        ?string $startTime = null,
        ?string $timezone = 'America/New_York',
        ?string $phone = null,
        array $guestEmails = [],
        array $questionsAnswers = [],
        array $tracking = []
    ): ?array {
        try {
            $payload = [
                'event_type' => $eventUri,
                'invitee' => [
                    'name' => $name,
                    'email' => $email,
                    'timezone' => $timezone ?? 'America/New_York',
                ],
            ];

            if ($startTime) {
                $payload['start_time'] = $startTime;
            }

            if ($phone) {
                $payload['invitee']['text_reminder_number'] = $phone;
            }

            if (! empty($guestEmails)) {
                $payload['event_guests'] = array_values(array_filter($guestEmails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
            }

            if (! empty($questionsAnswers)) {
                $payload['questions_and_answers'] = array_values($questionsAnswers);
            }

            if (! empty($tracking)) {
                // Calendly strictly requires: utm_campaign, utm_source, utm_medium, utm_content, utm_term, salesforce_uuid
                $payload['tracking'] = [
                    'utm_campaign' => ! empty($tracking['utm_campaign']) ? (string) $tracking['utm_campaign'] : null,
                    'utm_source' => ! empty($tracking['utm_source']) ? (string) $tracking['utm_source'] : 'remoteleverage_site',
                    'utm_medium' => ! empty($tracking['utm_medium']) ? (string) $tracking['utm_medium'] : null,
                    'utm_content' => ! empty($tracking['utm_content']) ? (string) $tracking['utm_content'] : null,
                    'utm_term' => ! empty($tracking['utm_term']) ? (string) $tracking['utm_term'] : null,
                    'salesforce_uuid' => ! empty($tracking['salesforce_uuid']) ? (string) $tracking['salesforce_uuid'] : null,
                ];
            }

            $response = $this->sendWithFailover(
                fn (string $token) => Http::withToken($token)
                    ->timeout(20)
                    ->post('https://api.calendly.com/invitees', $payload)
            );

            if (! $response) {
                Log::warning('CalendlyClient: No eligible tokens in pool (none configured, or all rate-limited/circuit-broken)');

                return null;
            }

            if ($response->failed()) {
                Log::error('CalendlyClient Invitee Creation Failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return null;
            }

            return $response->json('resource');
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Booking Exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Duplicate-booking preflight: does the given email already have an active
     * scheduled event for this exact event type + start time, on ANY pooled
     * account? Ported from legacy's is_already_booked(). Checked explicitly per
     * token (not via sendWithFailover) since the org/user URI used to scope the
     * /scheduled_events query is account-specific — the same token that resolved
     * it must be the one used to query with it.
     */
    public function findExistingInvitee(string $email, string $eventTypeUri, string $startTimeIso): ?array
    {
        foreach ($this->tokenPool->getEligibleTokens(null) as $item) {
            $token = $item['token'];

            $user = $this->getForToken($token, 'https://api.calendly.com/users/me');
            if (! $user || ! $user->successful()) {
                continue;
            }

            $orgUri = $user->json('resource.current_organization');
            $userUri = $user->json('resource.uri');
            if (! $orgUri && ! $userUri) {
                continue;
            }

            $response = $this->getForToken($token, 'https://api.calendly.com/scheduled_events', [
                'organization' => $orgUri,
                'invitee_email' => $email,
                'status' => 'active',
            ]);

            if (! $response || $response->status() === 403) {
                $response = $this->getForToken($token, 'https://api.calendly.com/scheduled_events', [
                    'user' => $userUri,
                    'invitee_email' => $email,
                    'status' => 'active',
                ]);
            }

            if (! $response || ! $response->successful()) {
                continue;
            }

            foreach ($response->json('collection', []) as $scheduledEvent) {
                $sameEventType = ($scheduledEvent['event_type'] ?? null) === $eventTypeUri;
                $sameStartTime = isset($scheduledEvent['start_time'])
                    && strtotime($scheduledEvent['start_time']) === strtotime($startTimeIso);

                if ($sameEventType && $sameStartTime) {
                    return $scheduledEvent;
                }
            }
        }

        return null;
    }

    /**
     * Cancel a scheduled event. Used when a repeat booking submission picks a
     * different slot than an earlier one for the same email — the earlier
     * meeting is superseded rather than left on the calendar as a stray
     * duplicate. Best-effort: failure here should never block the new
     * booking from succeeding, so callers should treat a false return as
     * "log it and move on," not a reason to fail the request.
     */
    public function cancelScheduledEvent(string $eventUriOrUuid, string $reason = 'Rescheduled to a new time.'): bool
    {
        $uuid = str_starts_with($eventUriOrUuid, 'http')
            ? basename(rtrim($eventUriOrUuid, '/'))
            : $eventUriOrUuid;

        try {
            $response = $this->sendWithFailover(
                fn (string $token) => Http::withToken($token)
                    ->timeout(15)
                    ->post("https://api.calendly.com/scheduled_events/{$uuid}/cancellation", [
                        'reason' => $reason,
                    ])
            );

            if (! $response || ! $response->successful()) {
                Log::warning('CalendlyClient: failed to cancel scheduled event', [
                    'event' => $eventUriOrUuid,
                    'status' => $response?->status(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Cancellation Exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Fetch scheduled event details by URI or UUID.
     */
    public function getScheduledEvent(string $eventUriOrUuid): ?array
    {
        $url = str_starts_with($eventUriOrUuid, 'http')
            ? $eventUriOrUuid
            : 'https://api.calendly.com/scheduled_events/'.rawurlencode($eventUriOrUuid);

        try {
            $response = $this->sendWithFailover(fn (string $token) => Http::withToken($token)->get($url));

            return $response && $response->successful() ? $response->json('resource') : null;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Event Detail Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Fetch invitee details by URI or UUID. Used by the Live Call concurrency
     * lock to self-heal when a "busy" session's invitee was actually canceled.
     */
    public function getInvitee(string $inviteeUriOrUuid): ?array
    {
        $url = str_starts_with($inviteeUriOrUuid, 'http')
            ? $inviteeUriOrUuid
            : 'https://api.calendly.com/scheduled_events/invitees/'.rawurlencode($inviteeUriOrUuid);

        try {
            $response = $this->sendWithFailover(fn (string $token) => Http::withToken($token)->get($url));

            return $response && $response->successful() ? $response->json('resource') : null;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Invitee Detail Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Poll a freshly-created scheduled event for its Google Meet location, since
     * Calendly's location data can lag slightly right after invitee creation.
     * Ported from JoinLiveCallIntegration's polling loop (up to 20x at 300ms).
     */
    public function pollForMeetLocation(string $scheduledEventUri, int $attempts = 20, int $delayMicroseconds = 300000): ?string
    {
        for ($i = 0; $i < $attempts; $i++) {
            $eventDetails = $this->getScheduledEvent($scheduledEventUri);
            $location = $eventDetails['location'] ?? [];

            $joinUrl = $location['join_url'] ?? $location['location'] ?? null;
            if ($joinUrl) {
                return $joinUrl;
            }

            $notes = $eventDetails['meeting_notes_plain'] ?? '';
            if (preg_match('#https://meet\.google\.com/[a-z0-9-]+#i', (string) $notes, $matches)) {
                return $matches[0];
            }

            if ($i < $attempts - 1) {
                usleep($delayMicroseconds);
            }
        }

        return null;
    }
}
