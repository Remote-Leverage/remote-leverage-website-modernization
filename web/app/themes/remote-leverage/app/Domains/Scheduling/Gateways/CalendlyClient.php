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
        $this->tokenPool = $tokenPool ?: new CalendlyTokenPool(config('services.calendly.api_keys', []));
        $this->userUri = config('services.calendly.user_uri');
    }

    /**
     * Send a Calendly request, failing over to the next pooled token if the current
     * one comes back rate-limited (HTTP 429). Tokens are tried in order (sequentially),
     * never proactively rotated, so the primary token is always preferred.
     */
    protected function sendWithFailover(callable $makeRequest): ?Response
    {
        $attempts = count($this->tokenPool->all());
        if ($attempts === 0) {
            return null;
        }

        $response = null;

        for ($i = 0; $i < $attempts; $i++) {
            $token = $this->tokenPool->getToken();
            if (! $token) {
                break;
            }

            $response = $makeRequest($token);

            if ($response->status() === 429) {
                Log::warning('CalendlyClient: token rate-limited, failing over to next token in pool');
                $this->tokenPool->markRateLimited($token);

                continue;
            }

            return $response;
        }

        return $response;
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
                Log::warning('CalendlyClient: Missing CALENDLY_API_KEY');

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
    public function getEventType(string $eventUriOrUuid): ?array
    {
        $url = str_starts_with($eventUriOrUuid, 'http')
            ? $eventUriOrUuid
            : 'https://api.calendly.com/event_types/'.rawurlencode($eventUriOrUuid);

        try {
            $response = $this->sendWithFailover(fn (string $token) => Http::withToken($token)->get($url));

            return $response && $response->successful() ? $response->json('resource') : null;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Event Type Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Fetch custom questions for an event type.
     */
    public function getEventQuestions(string $eventUriOrUuid): array
    {
        $eventType = $this->getEventType($eventUriOrUuid);

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
                Log::warning('CalendlyClient: Missing CALENDLY_API_KEY');

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
}
