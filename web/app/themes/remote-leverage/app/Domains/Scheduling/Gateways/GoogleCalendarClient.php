<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarClient
{
    protected ?string $clientId;

    protected ?string $clientSecret;

    protected ?string $refreshToken;

    protected string $calendarId;

    public function __construct()
    {
        $this->clientId = config('services.google_calendar.client_id');
        $this->clientSecret = config('services.google_calendar.client_secret');
        $this->refreshToken = config('services.google_calendar.refresh_token');
        $this->calendarId = config('services.google_calendar.calendar_id', 'primary');
    }

    /**
     * Get a fresh access token using the stored refresh token.
     */
    protected function getAccessToken(): ?string
    {
        if (! $this->clientId || ! $this->clientSecret || ! $this->refreshToken) {
            return null;
        }

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            return $response->successful() ? $response->json('access_token') : null;
        } catch (\Throwable $e) {
            Log::error('GoogleCalendarClient Token Refresh Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create a calendar appointment with Google Meet conference.
     */
    public function createAppointment(string $summary, string $startTime, string $endTime, array $attendees = [], ?string $description = null): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            Log::warning('GoogleCalendarClient: Could not acquire access token');

            return null;
        }

        try {
            $payload = [
                'summary' => $summary,
                'description' => $description ?? '',
                'start' => ['dateTime' => $startTime],
                'end' => ['dateTime' => $endTime],
                'attendees' => array_map(fn ($email) => ['email' => $email], $attendees),
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => uniqid('meet_', true),
                        'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                    ],
                ],
            ];

            $response = Http::withToken($token)
                ->post("https://www.googleapis.com/calendar/v3/calendars/{$this->calendarId}/events?conferenceDataVersion=1", $payload);

            if ($response->failed()) {
                Log::error('GoogleCalendarClient: Create Event Failed', $response->json());

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('GoogleCalendarClient Appointment Exception: '.$e->getMessage());

            return null;
        }
    }
}
