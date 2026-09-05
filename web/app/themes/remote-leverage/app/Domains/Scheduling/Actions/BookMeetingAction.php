<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\BookingRequestData;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\GoogleCalendarClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BookMeetingAction
{
    public function __construct(
        protected CalendlyClient $calendlyClient,
        protected GoogleCalendarClient $googleCalendarClient,
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

        // Path A: Calendly Direct Invitee Booking
        if ($calendlyEventUri) {
            $questionsAnswers = [];
            if (! empty($data->qualificationAnswers)) {
                foreach ($data->qualificationAnswers as $q => $a) {
                    $questionsAnswers[] = ['question' => $q, 'answer' => $a];
                }
            }

            $tracking = [
                'utm_campaign' => $data->referralCode ? 'ref_'.$data->referralCode : null,
                'utm_source' => 'remoteleverage_site',
            ];

            $invitee = $this->calendlyClient->createInvitee(
                eventUri: $calendlyEventUri,
                email: $data->email,
                name: $data->name,
                questionsAnswers: $questionsAnswers,
                tracking: array_filter($tracking),
            );

            if ($invitee) {
                return [
                    'success' => true,
                    'provider' => 'calendly',
                    'meeting_id' => $invitee['uri'] ?? uniqid('cal_', true),
                    'meet_url' => $invitee['scheduling_url'] ?? null,
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
}
