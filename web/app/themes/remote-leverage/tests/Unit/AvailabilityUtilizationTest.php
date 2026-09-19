<?php

declare(strict_types=1);

use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Scheduling\Services\AvailabilityHealthMonitor;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/**
 * The leading indicator that the 2026-09-17 sell-out did not have.
 *
 * Sold out is a lagging signal — by the time it fires the tier has already stopped earning, and
 * on the night in question it stayed that way for five hours. 90% and 95% are the two points at
 * which someone can still do something about it, and everything here is about the two ways that
 * kind of alert goes wrong: firing on a number it should not trust, and firing over and over
 * until the channel is muted.
 */

/** Captures what would have been sent, so the band logic can be asserted without a Slack double. */
class RecordingSlackTransport extends SlackTransport
{
    /** @var array<int, array{text: string, blocks: array, channel: ?string}> */
    public array $sent = [];

    public function post(
        string $text,
        array $blocks = [],
        ?string $color = null,
        ?string $threadTs = null,
        bool $broadcast = false,
        ?string $channel = null,
    ): ?array {
        $this->sent[] = ['text' => $text, 'blocks' => $blocks, 'channel' => $channel];

        return ['ts' => (string) count($this->sent), 'channel' => 'C1'];
    }
}

function utilizationMonitor(): array
{
    $transport = new RecordingSlackTransport;

    return [new AvailabilityHealthMonitor($transport), $transport];
}

/** Slot/booking counts that land on an exact fill percentage. */
function fillOf(float $percent, int $capacity = 100): array
{
    $booked = (int) round($capacity * $percent);

    return ['open' => $capacity - $booked, 'booked' => $booked];
}

describe('Tier utilization thresholds', function () {
    beforeEach(function () {
        Cache::flush();
        $GLOBALS['_wp_mock_options'] = [];

        config([
            'booking' => require __DIR__.'/../../config/booking.php',
            'slack-notifications' => require __DIR__.'/../../config/slack-notifications.php',
        ]);
    });

    test('the band boundaries are the two numbers that were asked for', function () {
        [$monitor] = utilizationMonitor();

        expect($monitor->bandForFill(0.899))->toBe(AvailabilityHealthMonitor::BAND_OK)
            ->and($monitor->bandForFill(0.90))->toBe(AvailabilityHealthMonitor::BAND_WARNING)
            ->and($monitor->bandForFill(0.949))->toBe(AvailabilityHealthMonitor::BAND_WARNING)
            ->and($monitor->bandForFill(0.95))->toBe(AvailabilityHealthMonitor::BAND_CRITICAL)
            ->and($monitor->bandForFill(1.0))->toBe(AvailabilityHealthMonitor::BAND_CRITICAL);

        /*
         * Never sold out, however full. A window with nothing left in it is not the same claim
         * as a tier with nothing bookable anywhere — only record(), which has looked past the
         * rolling window, can say that, and conflating them is the false alarm this whole
         * feature area was already burned by once.
         */
        expect($monitor->bandForFill(1.0))->not->toBe(AvailabilityHealthMonitor::BAND_SOLD_OUT);
    });

    test('crossing 90 warns once and sitting there stays quiet', function () {
        [$monitor, $slack] = utilizationMonitor();

        $at = fillOf(0.91);
        $monitor->recordUtilization('t10', 'https://api.calendly.com/event_types/abc', $at['open'], $at['booked'], 4);

        expect($slack->sent)->toHaveCount(1)
            ->and(json_encode($slack->sent[0]['blocks']))->toContain('filling up');

        // A tier hovering at 91% all afternoon is re-measured every few minutes. Without the
        // ladder this is where the channel becomes unreadable and the real alert gets muted
        // along with it.
        foreach ([0.90, 0.92, 0.94] as $fill) {
            $next = fillOf($fill);
            $monitor->recordUtilization('t10', 'https://api.calendly.com/event_types/abc', $next['open'], $next['booked'], 4);
        }

        expect($slack->sent)->toHaveCount(1);
    });

    test('escalating from 90 to 95 sends the second, worse message', function () {
        [$monitor, $slack] = utilizationMonitor();

        $warn = fillOf(0.91);
        $monitor->recordUtilization('t10', null, $warn['open'], $warn['booked'], 4);

        $crit = fillOf(0.96);
        $monitor->recordUtilization('t10', null, $crit['open'], $crit['booked'], 4);

        expect($slack->sent)->toHaveCount(2);

        $second = json_encode($slack->sent[1]['blocks']);

        expect($second)->toContain('Almost nothing left')
            ->and($second)->toContain('Critical')
            ->and($second)->toContain('4 slots left')
            ->and($second)->toContain('96 of 100 slots taken');
    });

    test('recovering is silent, and filling up again alerts again', function () {
        [$monitor, $slack] = utilizationMonitor();

        $crit = fillOf(0.96);
        $monitor->recordUtilization('t10', null, $crit['open'], $crit['booked'], 4);
        expect($slack->sent)->toHaveCount(1);

        // Someone extended the date range. Nothing to say about that.
        $ok = fillOf(0.40);
        $monitor->recordUtilization('t10', null, $ok['open'], $ok['booked'], 4);
        expect($slack->sent)->toHaveCount(1);

        // And it fills up again the next day, which is a new episode and worth hearing about.
        $warn = fillOf(0.91);
        $monitor->recordUtilization('t10', null, $warn['open'], $warn['booked'], 4);
        expect($slack->sent)->toHaveCount(2);
    });

    test('tiers escalate independently', function () {
        [$monitor, $slack] = utilizationMonitor();

        $crit = fillOf(0.97);
        $monitor->recordUtilization('t10', null, $crit['open'], $crit['booked'], 4);

        // t0 being healthy must not consume t10's escalation, and vice versa — t10 filling up
        // while t0 is fine is the exact shape of the incident.
        $ok = fillOf(0.20);
        $monitor->recordUtilization('t0', null, $ok['open'], $ok['booked'], 4);

        expect($slack->sent)->toHaveCount(1);

        $warn = fillOf(0.92);
        $monitor->recordUtilization('t0', null, $warn['open'], $warn['booked'], 4);

        expect($slack->sent)->toHaveCount(2)
            ->and(json_encode($slack->sent[1]['blocks'], JSON_UNESCAPED_SLASHES))->toContain('T0 (<$10k/mo)');
    });

    test('an unknown booked count is not treated as an empty calendar', function () {
        [$monitor, $slack] = utilizationMonitor();

        /*
         * The failure that would matter most. Calendly refusing the count returns null, and a
         * null coerced to 0 reads as "nothing booked" — the healthiest number there is — on
         * precisely the tier that is about to sell out.
         */
        $fill = $monitor->recordUtilization('t10', null, 2, null, 4);

        expect($fill)->toBeNull()
            ->and($slack->sent)->toBe([])
            ->and($monitor->status()['t10']['band'] ?? null)->toBeNull();
    });

    test('a window too small to have a percentage does not get one', function () {
        [$monitor, $slack] = utilizationMonitor();

        // Zero open and one booked is arithmetically 100% full. It is much more often a
        // calendar with no hours published, and paging someone for it is how the channel
        // learns to ignore this alert.
        $fill = $monitor->recordUtilization('t10', null, 0, 1, 4);

        expect($fill)->toBeNull()
            ->and($slack->sent)->toBe([]);

        // The raw counts are still stored — the admin panel should show what was seen even
        // when it is too thin to judge.
        expect($monitor->status()['t10']['open_slots'])->toBe(0)
            ->and($monitor->status()['t10']['fill'])->toBeNull();
    });

    test('a sell-out still repeats hourly even after a warning has fired', function () {
        [$monitor, $slack] = utilizationMonitor();

        $warn = fillOf(0.92);
        $monitor->recordUtilization('t10', null, $warn['open'], $warn['booked'], 4);
        expect($slack->sent)->toHaveCount(1);

        /*
         * The ladder must not swallow this. A sell-out is an active revenue stop rather than a
         * heads-up, so it keeps the independent hourly throttle it was given after the incident
         * — a tier that goes from 92% to sold out inside the hour has to produce both messages.
         */
        $monitor->record('t10', null, [], '2026-09', null);

        expect($slack->sent)->toHaveCount(2)
            ->and(json_encode($slack->sent[1]['blocks']))->toContain('No bookable times left');
    });

    test('a pageview sell-out check does not reset a tier back down the ladder', function () {
        [$monitor, $slack] = utilizationMonitor();

        $crit = fillOf(0.96);
        $monitor->recordUtilization('t10', null, $crit['open'], $crit['booked'], 4);
        expect($slack->sent)->toHaveCount(1);

        /*
         * record() knows nothing about fill. A tier it considers healthy may still be sitting
         * at 96%, so if this lowered the stored band the critical alert would re-fire on the
         * next measurement — and then on every one after it, because every pageview resets it.
         */
        $monitor->record('t10', null, ['2026-09-18'], '2026-09', '2026-09-18');

        $stillCritical = fillOf(0.97);
        $monitor->recordUtilization('t10', null, $stillCritical['open'], $stillCritical['booked'], 4);

        expect($slack->sent)->toHaveCount(1);
    });

    test('the warning renders from a config template, with no emoji and no styled buttons', function () {
        $rendered = (new SlackMessageRenderer)->render('availability_filling_up', [
            'tier_label' => 'T10 (≥$10k/mo)',
            'headline' => 'Booking window is filling up',
            'severity' => 'Warning',
            'fill_label' => '91.0% full',
            'remaining' => '9 slots left',
            'window_label' => 'next 4 days',
            'counts' => '91 of 100 slots taken',
            'admin_url' => 'https://example.test/wp/wp-admin/admin.php?page=rl-leads-diagnostics',
            'event_type_url' => 'https://calendly.com/event_types/user/me',
        ]);

        $encoded = json_encode($rendered['blocks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        expect($rendered['blocks'])->not->toBeEmpty()
            ->and(array_column($rendered['blocks'], 'type'))->toBe(['section', 'card', 'context', 'actions'])
            ->and($encoded)->toContain('9 slots left')
            // The line that stops a heads-up being escalated as an outage.
            ->and($encoded)->toContain('Nothing is broken yet');

        expect(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{25A0}-\x{25FF}]/u', $encoded))
            ->toBe(0);
    });

    test('one slot left is not described as "1 slots left"', function () {
        [$monitor, $slack] = utilizationMonitor();

        $monitor->recordUtilization('t10', null, 1, 99, 4);

        expect(json_encode($slack->sent[0]['blocks']))->toContain('1 slot left')
            ->and(json_encode($slack->sent[0]['blocks']))->not->toContain('1 slots left');
    });
});

describe('Counting the booked side', function () {
    beforeEach(function () {
        // See CalendlyPreflightTest: the Http factory is cached for the life of the process and
        // must be swapped outright, or stub closures leak between tests.
        Facade::clearResolvedInstance(HttpFactory::class);
        Http::swap(new HttpFactory);
        Cache::flush();
    });

    test('only events on the requested event type are counted', function () {
        $wanted = 'https://api.calendly.com/event_types/t10';

        Http::fake(function (Request $request) use ($wanted) {
            if (str_contains($request->url(), '/users/me')) {
                return Http::response(['resource' => [
                    'uri' => 'https://api.calendly.com/users/u1',
                    'current_organization' => 'https://api.calendly.com/organizations/o1',
                    'email' => 'ops@remoteleverage.com',
                ]], 200);
            }

            return Http::response([
                'collection' => [
                    ['event_type' => $wanted],
                    // The account's other event type shares the window and must not inflate
                    // t10's denominator — /scheduled_events has no event_type filter, so this
                    // separation only exists in PHP.
                    ['event_type' => 'https://api.calendly.com/event_types/t0'],
                    ['event_type' => $wanted],
                ],
                'pagination' => ['next_page' => null],
            ], 200);
        });

        $client = new CalendlyClient(new CalendlyTokenPool([
            ['label' => 'Pool 1', 'token' => 'tok-count-filter', 'enabled' => true],
        ]));

        expect($client->countBookedEvents($wanted, '2026-09-17T00:00:00Z', '2026-09-21T00:00:00Z'))->toBe(2);
    });

    test('pagination is followed', function () {
        $wanted = 'https://api.calendly.com/event_types/t10';
        $page = 0;

        Http::fake(function (Request $request) use ($wanted, &$page) {
            if (str_contains($request->url(), '/users/me')) {
                return Http::response(['resource' => [
                    'uri' => 'https://api.calendly.com/users/u1',
                    'current_organization' => 'https://api.calendly.com/organizations/o1',
                ]], 200);
            }

            $page++;

            return Http::response([
                'collection' => [['event_type' => $wanted], ['event_type' => $wanted]],
                'pagination' => ['next_page' => $page < 3
                    ? 'https://api.calendly.com/scheduled_events?page_token=p'.$page
                    : null],
            ], 200);
        });

        $client = new CalendlyClient(new CalendlyTokenPool([
            ['label' => 'Pool 1', 'token' => 'tok-count-pages', 'enabled' => true],
        ]));

        expect($client->countBookedEvents($wanted, '2026-09-17T00:00:00Z', '2026-09-21T00:00:00Z'))->toBe(6);
    });

    test('a failed lookup answers null, never zero', function () {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/users/me')) {
                return Http::response(['resource' => [
                    'uri' => 'https://api.calendly.com/users/u1',
                    'current_organization' => 'https://api.calendly.com/organizations/o1',
                ]], 200);
            }

            return Http::response(['message' => 'nope'], 500);
        });

        $client = new CalendlyClient(new CalendlyTokenPool([
            ['label' => 'Pool 1', 'token' => 'tok-count-fail', 'enabled' => true],
        ]));

        /*
         * The distinction the whole check rests on. Zero booked is the emptiest a calendar can
         * be, so handing it back for a lookup that failed would report a tier one booking from
         * selling out as completely open.
         */
        expect($client->countBookedEvents('https://api.calendly.com/event_types/t10', '2026-09-17T00:00:00Z', '2026-09-21T00:00:00Z'))
            ->toBeNull();
    });

    test('a token refused at the organization scope retries as the user', function () {
        $wanted = 'https://api.calendly.com/event_types/t10';

        Http::fake(function (Request $request) use ($wanted) {
            if (str_contains($request->url(), '/users/me')) {
                return Http::response(['resource' => [
                    'uri' => 'https://api.calendly.com/users/u1',
                    'current_organization' => 'https://api.calendly.com/organizations/o1',
                ]], 200);
            }

            // A token that can read its own calendar but not the organization's is a normal
            // Calendly setup, not a broken token.
            if (str_contains($request->url(), 'organization=')) {
                return Http::response(['message' => 'Permission denied'], 403);
            }

            return Http::response([
                'collection' => [['event_type' => $wanted]],
                'pagination' => ['next_page' => null],
            ], 200);
        });

        $client = new CalendlyClient(new CalendlyTokenPool([
            ['label' => 'Pool 1', 'token' => 'tok-count-scope', 'enabled' => true],
        ]));

        expect($client->countBookedEvents($wanted, '2026-09-17T00:00:00Z', '2026-09-21T00:00:00Z'))->toBe(1);
    });
});
