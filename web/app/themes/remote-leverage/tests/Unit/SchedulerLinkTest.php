<?php

declare(strict_types=1);

use App\Application\Http\Controllers\SchedulerLinkController;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Listeners\StampSchedulerLinkOnBooking;
use App\Domains\Scheduling\Support\SchedulerLink;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;

/**
 * /scheduler-link/?id=<event uuid>: the "add to calendar" link every legacy booking carried in
 * HubSpot `schedule_link`. It 404'd from the cutover, and v2 bookings never received one.
 */
const RL_EVENT_ID = '0b7c1a2e-3f4d-4e5f-8a9b-0c1d2e3f4a5b';

/** A Calendly client that answers one event and counts how often it was asked. */
function fakeCalendly(?array $event): CalendlyClient
{
    return new class($event) extends CalendlyClient
    {
        public int $calls = 0;

        public function __construct(private ?array $event)
        {
            // No token pool: this client never talks to Calendly.
        }

        public function getScheduledEvent(string $eventUriOrUuid): ?array
        {
            $this->calls++;

            return $this->event;
        }
    };
}

function calendlyEvent(array $overrides = []): array
{
    return array_merge([
        'uri' => 'https://api.calendly.com/scheduled_events/'.RL_EVENT_ID,
        'name' => 'VA Hiring Consultation T10 (A)',
        'status' => 'active',
        'start_time' => '2026-09-26T14:00:00.000000Z',
        'end_time' => '2026-09-26T14:30:00.000000Z',
        'location' => ['type' => 'google_conference', 'join_url' => 'https://meet.google.com/abc-defg-hij'],
        'event_memberships' => [['user_name' => 'Sample Rep', 'user_email' => 'rep@example.com']],
    ], $overrides);
}

function openLink(CalendlyClient $calendly, string $id, string $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
{
    $request = Request::create('https://remoteleverage.com/scheduler-link/', 'GET', ['id' => $id], [], [], ['HTTP_USER_AGENT' => $ua]);

    return (new SchedulerLinkController($calendly))($request);
}

beforeEach(function () {
    Cache::flush();
});

describe('SchedulerLink', function () {
    test('reads the scheduled event out of the invitee URI a booking reports', function () {
        $invitee = 'https://api.calendly.com/scheduled_events/'.RL_EVENT_ID.'/invitees/9e8d7c6b-5a4f-4e3d-8c2b-1a0f9e8d7c6b';

        expect(SchedulerLink::eventIdFrom($invitee))->toBe(RL_EVENT_ID)
            ->and(SchedulerLink::eventIdFrom(RL_EVENT_ID))->toBe(RL_EVENT_ID)
            ->and(SchedulerLink::eventIdFrom('uniqid-6710a'))->toBeNull()
            ->and(SchedulerLink::forMeeting($invitee))->toBe('https://remoteleverage.com/scheduler-link/?id='.RL_EVENT_ID);
    });

    test('builds a calendar file that survives commas, semicolons and newlines', function () {
        $entry = SchedulerLink::entryFor(calendlyEvent(['name' => 'Call; with, Remote Leverage']));
        $ics = SchedulerLink::ics($entry, RL_EVENT_ID);

        expect($ics)
            ->toContain("DTSTART:20260926T140000Z\r\n")
            ->toContain("DTEND:20260926T143000Z\r\n")
            ->toContain('SUMMARY:Call\; with\, Remote Leverage')
            ->toContain('LOCATION:https://meet.google.com/abc-defg-hij')
            ->toContain('DESCRIPTION:Organizer: Sample Rep (rep@example.com)\n\nLocation:')
            ->toContain('UID:'.RL_EVENT_ID.'@remoteleverage.com');
    });
});

describe('GET /scheduler-link/', function () {
    test('serves an .ics download that no cache may keep', function () {
        $response = openLink(fakeCalendly(calendlyEvent()), RL_EVENT_ID);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->headers->get('Content-Type'))->toContain('text/calendar')
            ->and($response->headers->get('Content-Disposition'))->toContain('scheduled-call.ics')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->getContent())->toContain('BEGIN:VEVENT');
    });

    test('sends Android to Google Calendar, as the legacy route did', function () {
        $response = openLink(fakeCalendly(calendlyEvent()), RL_EVENT_ID, 'Mozilla/5.0 (Linux; Android 14; Pixel 8)');

        expect($response->getStatusCode())->toBe(302)
            ->and($response->headers->get('Location'))->toStartWith('https://calendar.google.com/calendar/render?action=TEMPLATE')
            ->and($response->headers->get('Location'))->toContain('dates=20260926T140000Z%2F20260926T143000Z');
    });

    test('refuses anything not shaped like an event id without asking Calendly', function () {
        $calendly = fakeCalendly(calendlyEvent());

        expect(openLink($calendly, '../users/me')->getStatusCode())->toBe(404)
            ->and(openLink($calendly, '')->getStatusCode())->toBe(404)
            ->and($calendly->calls)->toBe(0);
    });

    test('404s an event Calendly does not have, or that was canceled', function () {
        expect(openLink(fakeCalendly(null), RL_EVENT_ID)->getStatusCode())->toBe(404);

        Cache::flush();

        expect(openLink(fakeCalendly(calendlyEvent(['status' => 'canceled'])), RL_EVENT_ID)->getStatusCode())->toBe(404);
    });

    test('caches the answer, so a reopened link costs the booking token pool nothing', function () {
        $calendly = fakeCalendly(calendlyEvent());

        openLink($calendly, RL_EVENT_ID);
        openLink($calendly, RL_EVENT_ID);

        expect($calendly->calls)->toBe(1);
    });

    test('stops asking Calendly once the per-minute budget is spent', function () {
        $calendly = fakeCalendly(calendlyEvent());
        Cache::put('rl_scheduler_link_budget_'.intdiv(time(), 60), 60, 120);

        $response = openLink($calendly, RL_EVENT_ID);

        expect($response->getStatusCode())->toBe(429)
            ->and($calendly->calls)->toBe(0);
    });

    test('is exempt from the page caches', function () {
        $nginx = file_get_contents(__DIR__.'/../../../../../../docker/nginx.conf');
        $routes = file_get_contents(__DIR__.'/../../routes/web.php');

        expect($nginx)->toContain('"~*^/scheduler-link" 1;')
            ->and($routes)->toContain("Route::get('scheduler-link', SchedulerLinkController::class)");
    });
});

/** A command bus that records what was deferred instead of running it. */
function schedulerLinkBusSpy(): object
{
    $spy = new class implements DispatcherContract
    {
        public array $deferred = [];

        public function dispatch($command)
        {
            $this->deferred[] = $command;
        }

        public function dispatchAfterResponse($command, $handler = null)
        {
            $this->deferred[] = $command;
        }

        public function dispatchSync($command, $handler = null) {}

        public function dispatchNow($command, $handler = null) {}

        public function hasCommandHandler($command)
        {
            return false;
        }

        public function getCommandHandler($command)
        {
            return false;
        }

        public function pipeThrough(array $pipes)
        {
            return $this;
        }

        public function map(array $map)
        {
            return $this;
        }

        public function chain($jobs = null)
        {
            return $this;
        }
    };

    Facade::getFacadeApplication()->instance(DispatcherContract::class, $spy);

    return $spy;
}

describe('StampSchedulerLinkOnBooking', function () {
    afterEach(function () {
        Facade::getFacadeApplication()->forgetInstance(DispatcherContract::class);
    });

    function bookedLead(array $attributes = []): Lead
    {
        // forceFill: `is_blocked` is deliberately not mass-assignable.
        $lead = (new Lead)->forceFill(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pat Booker',
            'email' => 'pat.'.Str::random(6).'@example.org',
            'source_type' => 'ad',
            'status' => 'booked',
        ], $attributes));
        $lead->save();

        return $lead;
    }

    test('stamps the new booking\'s link on the lead, then defers the HubSpot sync by id', function () {
        $bus = schedulerLinkBusSpy();
        $lead = bookedLead(['scheduler_link' => 'https://remoteleverage.com/scheduler-link?id=11111111-2222-4333-8444-555555555555']);
        $invitee = 'https://api.calendly.com/scheduled_events/'.RL_EVENT_ID.'/invitees/9e8d7c6b-5a4f-4e3d-8c2b-1a0f9e8d7c6b';

        (new StampSchedulerLinkOnBooking)->handle(new LeadBookingCompleted($lead, $invitee, 'calendly'));

        expect($lead->fresh()->scheduler_link)->toBe('https://remoteleverage.com/scheduler-link/?id='.RL_EVENT_ID)
            ->and($bus->deferred)->toHaveCount(1)
            ->and($bus->deferred[0]->handler)->toBe(StampSchedulerLinkOnBooking::class)
            ->and($bus->deferred[0]->method)->toBe('syncToHubSpot')
            ->and($bus->deferred[0]->arguments)->toBe([(int) $lead->id]);
    });

    test('leaves a blocked lead, and a non-Calendly booking, alone', function () {
        $bus = schedulerLinkBusSpy();
        $invitee = 'https://api.calendly.com/scheduled_events/'.RL_EVENT_ID.'/invitees/9e8d7c6b-5a4f-4e3d-8c2b-1a0f9e8d7c6b';

        $blocked = bookedLead(['is_blocked' => true]);
        (new StampSchedulerLinkOnBooking)->handle(new LeadBookingCompleted($blocked, $invitee, 'calendly'));

        $google = bookedLead();
        (new StampSchedulerLinkOnBooking)->handle(new LeadBookingCompleted($google, 'gcal-123', 'google'));

        expect($blocked->fresh()->scheduler_link)->toBeNull()
            ->and($google->fresh()->scheduler_link)->toBeNull()
            ->and($bus->deferred)->toBe([]);
    });
});
