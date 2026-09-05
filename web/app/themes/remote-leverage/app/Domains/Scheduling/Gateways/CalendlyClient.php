<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendlyClient
{
    protected ?string $apiKey;

    protected ?string $userUri;

    public function __construct()
    {
        $this->apiKey = config('services.calendly.api_key');
        $this->userUri = config('services.calendly.user_uri');
    }

    /**
     * Fetch event type availability slots from Calendly.
     * Ported from CalendlyIntegration::get_availability().
     */
    public function getAvailableSlots(string $eventTypeId, string $startTime, string $endTime): array
    {
        if (! $this->apiKey) {
            Log::warning('CalendlyClient: Missing CALENDLY_API_KEY');

            return [];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(15)
                ->get('https://api.calendly.com/event_type_available_times', [
                    'event_type' => $eventTypeId,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);

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
     * Book appointment by creating invitee on Calendly.
     * Ported from CalendlyIntegration::process_calendly_booking() / api.calendly.com/invitees.
     */
    public function createInvitee(string $eventUri, string $email, string $name, array $questionsAnswers = [], array $tracking = []): ?array
    {
        if (! $this->apiKey) {
            Log::warning('CalendlyClient: Missing CALENDLY_API_KEY');

            return null;
        }

        try {
            $payload = [
                'event' => $eventUri,
                'email' => $email,
                'name' => $name,
            ];

            if (! empty($questionsAnswers)) {
                $payload['questions_and_answers'] = $questionsAnswers;
            }

            if (! empty($tracking)) {
                $payload['tracking'] = $tracking;
            }

            $response = Http::withToken($this->apiKey)
                ->timeout(20)
                ->post('https://api.calendly.com/invitees', $payload);

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
     * Fetch scheduled event details.
     */
    public function getScheduledEvent(string $eventUuid): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->get('https://api.calendly.com/scheduled_events/'.rawurlencode($eventUuid));

            return $response->successful() ? $response->json('resource') : null;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Event Detail Error: '.$e->getMessage());

            return null;
        }
    }
}
