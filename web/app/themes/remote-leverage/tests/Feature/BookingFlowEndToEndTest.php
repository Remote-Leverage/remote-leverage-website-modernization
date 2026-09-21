<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Scheduling\Listeners\HandleLeadCreatedForBooking;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/**
 * The booking path end to end: a visitor submitting the wizard, through lead capture and the
 * Scheduling listener, to what the provider did and what the visitor is then shown.
 *
 * ## Why this did not exist before
 *
 * Every layer had tests and the path between them had none, which is exactly how five people were
 * told a consultant was expecting them between 19 and 21 September. `BookMeetingAction` was
 * covered in isolation, the wizard's validation was covered, the retry ladder was covered — and
 * nothing ever ran a booking from submit to screen, so nothing noticed that a failure at the
 * bottom arrived at the top as a confirmation.
 *
 * The mechanical reason is worth recording: `submitBooking()` takes a `Cache::lock()` as its
 * first real act, the test harness's cache stub had no `lock()`, and so every attempt to drive
 * this method died with `Call to undefined method ::lock()` before reaching any booking. One
 * missing stub method kept the whole submit path untestable. It is in `tests/stubs.php` now.
 *
 * ## What is real here and what is not
 *
 * Real: the wizard, `CaptureLeadAction`, the `LeadCreated` event, `HandleLeadCreatedForBooking`,
 * `BookMeetingAction`, `CalendlyClient`, the retry ladder, the lead row and the activity log.
 * Faked: Calendly's HTTP responses, which is the only seam that should be faked — it is the one
 * thing these tests are about being honest about.
 */
function calendlyWizard(string $email, string $slot = '2026-09-25T16:00:00Z'): MultistepBookingWizard
{
    $wizard = new MultistepBookingWizard;
    $wizard->mount('test', 'glass');

    $wizard->email = $email;
    $wizard->firstName = 'Susan';
    $wizard->lastName = 'Ornstein';
    $wizard->phone = '+1 732 771 7195';
    $wizard->consent = true;
    $wizard->monthlyRevenue = '$10k to $50k Per Month';
    $wizard->timezone = 'America/New_York';
    $wizard->selectedSlot = $slot;

    return $wizard;
}

/** Every Scheduling row's meeting id for a lead, newest last. */
function meetingIdsFor(Lead $lead): array
{
    return LeadActivityLog::query()
        ->where('lead_id', $lead->id)
        ->where('actor_domain', 'Scheduling')
        ->orderBy('created_at')
        ->pluck('payload')
        ->map(fn ($payload) => is_array($payload) ? ($payload['meeting_id'] ?? null) : null)
        ->all();
}

beforeEach(function () {
    Lead::query()->forceDelete();
    LeadActivityLog::query()->delete();

    // See CalendlyPreflightTest: the Http factory outlives a single test and must be swapped.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);

    app()->singleton(CalendlyTokenPool::class, fn () => new CalendlyTokenPool([
        ['label' => 'Pool 1', 'token' => 'tok-e2e', 'enabled' => true],
    ]));

    app()->instance(CalendlyEventTypeRoleResolver::class, new class extends CalendlyEventTypeRoleResolver
    {
        public function get(string $role): ?string
        {
            return 'https://api.calendly.com/event_types/t10';
        }
    });

    /*
     * The wiring SchedulingServiceProvider::boot() does in production. Nothing listens to
     * LeadCreated in the test environment by default, which is why `afterEach` can clear the
     * event wholesale without taking anything else with it.
     */
    Event::listen(LeadCreated::class, [HandleLeadCreatedForBooking::class, 'handle']);
});

afterEach(function () {
    Event::forget(LeadCreated::class);

    /*
     * Both of these are rebound above, and a container binding outlives the test that made it.
     * Left in place, the role resolver that always answers "t10" made BookingWizardRoutingTest
     * fail for the rest of the run — a test asserting the T0/T10 split cannot be run against a
     * resolver that only knows one of them. `offsetUnset` drops binding and instance together,
     * so the container goes back to auto-resolving the real classes.
     */
    app()->offsetUnset(CalendlyTokenPool::class);
    app()->offsetUnset(CalendlyEventTypeRoleResolver::class);
});

describe('a booking that Calendly takes', function () {
    test('the lead is booked, the meeting id is Calendly\'s, and the visitor is confirmed', function () {
        Http::fake([
            'api.calendly.com/scheduled_events*' => Http::response([
                'resource' => ['location' => ['join_url' => 'https://meet.google.com/real-room']],
            ], 200),
            'api.calendly.com/invitees*' => Http::response([
                'resource' => [
                    'uri' => 'https://api.calendly.com/scheduled_events/abc/invitees/def',
                    'event' => 'https://api.calendly.com/scheduled_events/abc',
                ],
            ], 201),
            '*' => Http::response(['collection' => []], 200),
        ]);

        $wizard = calendlyWizard('booked@example.com');

        try {
            $wizard->submitBooking();
        } catch (Throwable $e) {
            // The success path ends in a Livewire redirect, which needs a request lifecycle this
            // harness does not have. Everything asserted below is set before it.
        }

        $lead = Lead::query()->firstWhere('email', 'booked@example.com');

        expect($lead)->not->toBeNull()
            ->and($lead->status)->toBe('booked')
            ->and($lead->booking_retry_count)->toBe(0)
            ->and($lead->booking_next_retry_at)->toBeNull()
            ->and(meetingIdsFor($lead))->toContain('https://api.calendly.com/scheduled_events/abc/invitees/def');
    });
});

describe('a booking no provider takes', function () {
    /*
     * The Marvin Rodriguez case, 2026-09-21 10:54 ET. Before the fix this reached the visitor as
     * a confirmation, carrying `gcal_6ab1450a4b7021.28353181` and a `meet.google.com/rl-consult`
     * room that has never existed, and went on to fire two live Google Ads conversions.
     */
    test('nothing is invented, the lead is not booked, and the retry ladder has it', function () {
        Http::fake(['*' => Http::response([], 503)]);

        $wizard = calendlyWizard('unreachable@example.com');
        $wizard->submitBooking();

        $lead = Lead::query()->firstWhere('email', 'unreachable@example.com');

        expect($lead->status)->not->toBe('booked')
            ->and($lead->booking_retry_count)->toBe(1)
            ->and($lead->booking_next_retry_at)->not->toBeNull();

        // No meeting was invented anywhere, under any prefix this codebase has ever minted.
        foreach (meetingIdsFor($lead) as $meetingId) {
            expect($meetingId)->toBeNull();
        }

        // And the booking was never announced: no LeadBookingCompleted row means no Slack "final"
        // card, no lead.booking_completed webhook and no referrer attribution.
        expect(LeadActivityLog::query()->where('event_type', 'LeadBookingCompleted')->count())->toBe(0);
    });

    /*
     * Calendly not answering at all, which is a different thing from Calendly saying no and is
     * the one that matters here: a refusal returns early with its own code, so only this case
     * reaches the foot of `execute()` where the Google fallback used to sit. Reinstating that
     * fallback has to fail this test, or removing it was never covered.
     */
    test('Calendly never answering is a failure, with no second provider behind it', function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $wizard = calendlyWizard('timeout@example.com');
        $wizard->submitBooking();

        $lead = Lead::query()->firstWhere('email', 'timeout@example.com');

        expect($lead->status)->not->toBe('booked')
            ->and($wizard->isBooked)->toBeFalse()
            ->and(LeadActivityLog::query()->where('event_type', 'LeadBookingCompleted')->count())->toBe(0);

        foreach (meetingIdsFor($lead) as $meetingId) {
            expect($meetingId)->toBeNull();
        }

        // The failure row names the reason, so an outage is legible in the log rather than being
        // a booking that quietly has no meeting.
        $failure = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'Scheduling')
            ->where('outcome', 'failed')
            ->first();

        expect($failure)->not->toBeNull();
    });

    test('the visitor is told it is unconfirmed rather than shown a confirmation', function () {
        Http::fake(['*' => Http::response([], 503)]);

        $wizard = calendlyWizard('unreachable2@example.com');
        $wizard->submitBooking();

        expect($wizard->isBooked)->toBeFalse()
            ->and($wizard->errorMessage)->toContain('still confirming it with the calendar')
            // Staying on the picker is what keeps /VAThankYou/ — and the two Ads conversions it
            // fires — out of reach until a meeting exists.
            ->and($wizard->currentStep)->toBe(3);
    });
});

describe('a slot taken between the picker and the submit', function () {
    test('the visitor is sent back to choose again, not put on a 32-minute ladder', function () {
        Http::fake([
            'api.calendly.com/invitees*' => Http::response([
                'title' => 'Invalid Argument',
                'details' => [['parameter' => 'event.start_time', 'code' => 'already_filled']],
            ], 400),
            '*' => Http::response(['collection' => []], 200),
        ]);

        $wizard = calendlyWizard('taken@example.com');
        $wizard->submitBooking();

        $lead = Lead::query()->firstWhere('email', 'taken@example.com');

        expect($lead->status)->toBe('booking_failed')
            ->and($lead->booking_next_retry_at)->toBeNull()
            ->and($wizard->isBooked)->toBeFalse()
            ->and($wizard->errorMessage)->toContain('booked by someone else')
            ->and($wizard->selectedSlot)->toBeNull()
            ->and($wizard->currentStep)->toBe(3);
    });
});
