<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Scheduling\Actions\BookMeetingAction;
use App\Domains\Scheduling\Concerns\HandlesBookingRetryBackoff;
use App\Domains\Scheduling\Data\BookingRequestData;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\CalendlyMetadataCache;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A slot taken between the picker rendering and the submit.
 *
 * Calendly answers that with 400 `already_filled`, and on 2026-09-20 it produced a lead marked
 * booked, a confirmation screen, a Google Ads conversion and no meeting — because every layer
 * below the refusal treated "Calendly said no" as "Calendly did not answer".
 */
const SLOT_TAKEN_BODY = [
    'title' => 'Invalid Argument',
    'message' => 'The supplied parameters are invalid.',
    'details' => [
        ['parameter' => 'event.start_time', 'message' => 'That start time has been filled', 'code' => 'already_filled'],
    ],
];

function bookingRequest(): BookingRequestData
{
    return BookingRequestData::fromArray([
        'name' => 'Susan Ornstein',
        'email' => 'sornstein@example.com',
        'start_time' => '2026-09-21T16:00:00Z',
        'timezone' => 'America/New_York',
    ]);
}

beforeEach(function () {
    // See CalendlyPreflightTest: the Http factory outlives a single test and must be swapped.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);
});

describe('a slot taken out from under a booking', function () {
    test('the client keeps the code Calendly refused with, and clears it on the next success', function () {
        Http::fake([
            'api.calendly.com/invitees' => Http::sequence()
                ->push(SLOT_TAKEN_BODY, 400)
                ->push(['resource' => ['uri' => 'https://api.calendly.com/invitees/ok']], 201),
        ]);

        $client = new CalendlyClient(new CalendlyTokenPool([
            ['label' => 'Pool 1', 'token' => 'tok-slot-taken', 'enabled' => true],
        ]));

        expect($client->createInvitee('https://api.calendly.com/event_types/t10', 'a@b.com', 'A B'))->toBeNull()
            ->and($client->lastInviteeErrorCode())->toBe('already_filled');

        // Stale state here would condemn the next booking for the last one's failure.
        expect($client->createInvitee('https://api.calendly.com/event_types/t10', 'a@b.com', 'A B'))->not->toBeNull()
            ->and($client->lastInviteeErrorCode())->toBeNull();
    });

    test('a refused slot is a failure, with nowhere left to fall through to', function () {
        Http::fake(['api.calendly.com/*' => Http::response(SLOT_TAKEN_BODY, 400)]);

        $activityLogger = $this->createMock(LeadActivityLogger::class);
        $activityLogger->method('findRecentBookingForSlot')->willReturn(null);

        $metadata = $this->createMock(CalendlyMetadataCache::class);
        $metadata->method('getEventQuestions')->willReturn([]);

        $roles = $this->createMock(CalendlyEventTypeRoleResolver::class);
        $roles->method('get')->willReturn('https://api.calendly.com/event_types/t10');

        $pool = new CalendlyTokenPool([['label' => 'Pool 1', 'token' => 'tok-book', 'enabled' => true]]);

        $action = new BookMeetingAction(
            new CalendlyClient($pool),
            $pool,
            $roles,
            $metadata,
            $activityLogger,
        );

        $result = $action->execute(bookingRequest());

        expect($result['success'])->toBeFalse()
            ->and($result['error_code'])->toBe('slot_taken');
    });

    test('no usable Calendly credential is a failure, not a confirmation for a meeting nobody has', function () {
        /*
         * No pooled tokens, so Calendly is skipped. This used to be the point where the Google
         * fallback took over and invented a booking; there is no second provider now.
         */
        $activityLogger = $this->createMock(LeadActivityLogger::class);
        $activityLogger->method('findRecentBookingForSlot')->willReturn(null);

        $metadata = $this->createMock(CalendlyMetadataCache::class);
        $metadata->method('getEventQuestions')->willReturn([]);

        $roles = $this->createMock(CalendlyEventTypeRoleResolver::class);
        $roles->method('get')->willReturn('https://api.calendly.com/event_types/t10');

        $pool = new CalendlyTokenPool([]);

        $action = new BookMeetingAction(
            new CalendlyClient($pool),
            $pool,
            $roles,
            $metadata,
            $activityLogger,
        );

        $result = $action->execute(bookingRequest());

        /*
         * This used to be success: true, with a uniqid() meeting id and a hardcoded
         * meet.google.com/rl-consult that is not even a valid Meet code.
         */
        expect($result['success'])->toBeFalse()
            ->and($result['error_code'])->toBe('provider_unavailable')
            ->and($result)->not->toHaveKey('meet_url');
    });

    /*
     * The 19–21 September phantoms, and why there is one provider now.
     *
     * Five leads were marked booked carrying a `uniqid()` this code minted when a provider
     * returned nothing nameable — the last at 10:54 ET on 21 September, with a `gcal_` id and
     * `https://meet.google.com/rl-consult` for a meeting on no calendar. Every one of them came
     * through the Google fallback, which in the whole history of this application produced five
     * bookings and zero real events. It is gone; this is the same failure mode on the path that
     * remains.
     */
    test('a Calendly invitee with no uri is a failure, not a made-up reference', function () {
        // 201, and nothing in it names the invitee Calendly says it created.
        Http::fake(['api.calendly.com/*' => Http::response(['resource' => ['status' => 'active']], 201)]);

        $activityLogger = $this->createMock(LeadActivityLogger::class);
        $activityLogger->method('findRecentBookingForSlot')->willReturn(null);

        $metadata = $this->createMock(CalendlyMetadataCache::class);
        $metadata->method('getEventQuestions')->willReturn([]);

        $roles = $this->createMock(CalendlyEventTypeRoleResolver::class);
        $roles->method('get')->willReturn('https://api.calendly.com/event_types/t10');

        $pool = new CalendlyTokenPool([['label' => 'Pool 1', 'token' => 'tok-no-uri', 'enabled' => true]]);

        $result = (new BookMeetingAction(
            new CalendlyClient($pool), $pool, $roles, $metadata, $activityLogger,
        ))->execute(bookingRequest());

        expect($result['success'])->toBeFalse()
            ->and($result['error_code'])->toBe('provider_unavailable')
            ->and($result)->not->toHaveKey('meeting_id');
    });

    test('a prior booking log naming no meeting is not replayed as a success', function () {
        $stale = new LeadActivityLog(['payload' => ['provider' => 'google_calendar', 'meet_url' => null]]);

        $activityLogger = $this->createMock(LeadActivityLogger::class);
        $activityLogger->method('findRecentBookingForSlot')->willReturn($stale);

        $metadata = $this->createMock(CalendlyMetadataCache::class);
        $metadata->method('getEventQuestions')->willReturn([]);

        $roles = $this->createMock(CalendlyEventTypeRoleResolver::class);
        $roles->method('get')->willReturn('https://api.calendly.com/event_types/t10');

        $pool = new CalendlyTokenPool([]);

        $result = (new BookMeetingAction(
            new CalendlyClient($pool), $pool, $roles, $metadata, $activityLogger,
        ))->execute(bookingRequest());

        /*
         * Otherwise one phantom booking becomes the answer to every retry for the next five
         * minutes, and the failure confirms itself.
         */
        expect($result['success'])->toBeFalse()
            ->and($result['error_code'])->toBe('provider_unavailable');
    });

    test('a slot that is gone is not put on the retry ladder', function () {
        LeadActivityLog::truncate();
        Lead::truncate();

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Susan Ornstein',
            'email' => 'sornstein@example.com',
            'status' => 'booking_pending',
        ]);

        $recorder = new class
        {
            use HandlesBookingRetryBackoff;

            public function record(Lead $lead, bool $retryable): void
            {
                $this->recordBookingFailureAndMaybeReschedule(
                    $lead,
                    bookingRequest(),
                    'https://api.calendly.com/event_types/t10',
                    'That time was taken by someone else before the booking went through.',
                    new LeadActivityLogger,
                    retryable: $retryable,
                );
            }
        };

        $recorder->record($lead, false);
        $lead->refresh();

        /*
         * Five attempts over 32 minutes would all re-submit the same dead time, and the lead
         * would spend half an hour believing a booking was in flight.
         */
        expect($lead->status)->toBe('booking_failed')
            ->and($lead->booking_next_retry_at)->toBeNull();
    });
});
