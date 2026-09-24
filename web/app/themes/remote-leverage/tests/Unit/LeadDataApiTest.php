<?php

declare(strict_types=1);

use App\Ai\InsightsCapability;
use App\Domains\Lead\Api\LeadDataFeed;
use App\Domains\Lead\Api\LeadDataRestRoutes;
use App\Domains\Lead\Api\LeadDataWindow;
use App\Domains\Lead\Export\LeadExportColumns;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadBookings;
use Carbon\CarbonImmutable;

/*
 * The data team's leads and bookings export (`/wp-json/rl-data/v1/{leads,bookings}`).
 *
 * What is worth pinning is what a pipeline gets wrong silently: a boundary row loaded into two
 * windows, a page that skips a row, a booking counted three times, a timezone read as UTC, and a
 * field that quietly changes its name. None of those fail; they each produce a plausible number.
 */

/** `created_at` is not fillable, so it is set after the insert — see costAlertLead(). */
function dataApiLead(string $createdAt, array $attributes = []): Lead
{
    static $sequence = 0;
    $sequence++;

    $lead = Lead::create(array_merge([
        'uuid' => 'data-api-'.$sequence,
        'name' => 'Data '.$sequence,
        'email' => 'data'.$sequence.'@example.com',
        'phone' => '+1305555'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
        'phone_country' => 'US',
        'status' => 'captured',
        'source_type' => 'organic',
    ], $attributes));

    $lead->created_at = $createdAt;
    $lead->save();

    return $lead;
}

function dataApiBooking(Lead $lead, string $at, string $actor = 'Slack'): void
{
    LeadActivityLog::create([
        'lead_id' => $lead->id,
        'event_type' => LeadBookings::EVENT,
        'actor_domain' => $actor,
        'stage' => 'consumption',
        'outcome' => 'succeeded',
        'created_at' => $at,
    ]);
}

function dataApiWindow(string $from, string $to): LeadDataWindow
{
    $window = LeadDataWindow::parse($from, $to);

    expect($window)->toBeInstanceOf(LeadDataWindow::class);

    return $window;
}

beforeEach(function () {
    Lead::withTrashed()->forceDelete();
    LeadActivityLog::query()->delete();
});

afterEach(function () {
    unset($GLOBALS['_wp_mock_capabilities'], $GLOBALS['wp_current_user_logged_in']);
});

describe('LeadDataWindow', function () {
    test('both ends are required', function () {
        $error = LeadDataWindow::parse('2026-09-01', null);

        expect($error)->toBeInstanceOf(WP_Error::class)
            ->and($error->get_error_code())->toBe('rl_data_missing_window');
    });

    test('a date without an offset is New York midnight, not UTC midnight', function () {
        // September 1st starts at 04:00 UTC during EDT. Reading it as UTC would put the first four
        // hours of the business's day into the previous window.
        expect(dataApiWindow('2026-09-01', '2026-09-02')->toArray())
            ->toBe(['from' => '2026-09-01T04:00:00Z', 'to' => '2026-09-02T04:00:00Z']);
    });

    test('an explicit offset is honoured', function () {
        expect(dataApiWindow('2026-09-01T00:00:00Z', '2026-09-01T12:00:00-04:00')->toArray())
            ->toBe(['from' => '2026-09-01T00:00:00Z', 'to' => '2026-09-01T16:00:00Z']);
    });

    test('a plus sign that arrived as a space is still read as an offset', function () {
        // `?from=2026-09-01T00:00:00+02:00` unencoded; PHP hands us a space where the + was.
        expect(dataApiWindow('2026-09-01T00:00:00 02:00', '2026-09-02T00:00:00Z')->toArray()['from'])
            ->toBe('2026-08-31T22:00:00Z');
    });

    test('a relative date is refused rather than resolved against the clock', function () {
        expect(LeadDataWindow::parse('yesterday', 'today')->get_error_code())->toBe('rl_data_invalid_datetime');
    });

    test('an impossible date is refused rather than rolled into the next month', function () {
        expect(LeadDataWindow::parse('2026-02-30', '2026-03-05')->get_error_code())->toBe('rl_data_invalid_datetime');
    });

    test('an empty or inverted window is refused', function () {
        expect(LeadDataWindow::parse('2026-09-02', '2026-09-02')->get_error_code())->toBe('rl_data_empty_window')
            ->and(LeadDataWindow::parse('2026-09-03', '2026-09-02')->get_error_code())->toBe('rl_data_empty_window');
    });

    test('a window longer than a year is refused', function () {
        expect(LeadDataWindow::parse('2025-01-01', '2026-09-01')->get_error_code())->toBe('rl_data_window_too_long')
            ->and(LeadDataWindow::parse('2025-09-01', '2026-09-01'))->toBeInstanceOf(LeadDataWindow::class);
    });
});

describe('leads', function () {
    test('the window includes its start and excludes its end, so adjacent pulls partition', function () {
        dataApiLead('2026-09-01 04:00:00', ['name' => 'On the start']);
        dataApiLead('2026-09-01 20:00:00', ['name' => 'Inside']);
        dataApiLead('2026-09-02 04:00:00', ['name' => 'On the end']);

        $feed = new LeadDataFeed;
        $first = $feed->leads(dataApiWindow('2026-09-01', '2026-09-02'), ['contact']);
        $second = $feed->leads(dataApiWindow('2026-09-02', '2026-09-03'), ['contact']);

        expect(array_column($first['data'], 'full_name'))->toBe(['On the start', 'Inside'])
            ->and(array_column($second['data'], 'full_name'))->toBe(['On the end']);
    });

    test('paging walks every row exactly once and says when it is done', function () {
        foreach (range(1, 5) as $hour) {
            dataApiLead(sprintf('2026-09-01 %02d:00:00', $hour + 10));
        }

        $feed = new LeadDataFeed;
        $window = dataApiWindow('2026-09-01', '2026-09-02');
        $seen = [];
        $cursor = 0;
        $pages = 0;

        do {
            $page = $feed->leads($window, [], $cursor, 2);
            $seen = array_merge($seen, array_column($page['data'], 'id'));
            $cursor = (int) $page['next_cursor'];
            $pages++;

            expect($page['total'])->toBe(5);
        } while ($page['next_cursor'] !== null && $pages < 10);

        expect($pages)->toBe(3)
            ->and($seen)->toHaveCount(5)
            ->and(array_unique($seen))->toHaveCount(5);
    });

    test('a lead captured mid-pull lands on a later page instead of shifting the one being read', function () {
        dataApiLead('2026-09-01 12:00:00');
        dataApiLead('2026-09-01 13:00:00');
        dataApiLead('2026-09-01 14:00:00');

        $feed = new LeadDataFeed;
        $window = dataApiWindow('2026-09-01', '2026-09-02');
        $first = $feed->leads($window, [], 0, 2);

        dataApiLead('2026-09-01 11:00:00', ['name' => 'Late arrival']);

        $second = $feed->leads($window, [], (int) $first['next_cursor'], 2);

        expect(array_merge(array_column($first['data'], 'id'), array_column($second['data'], 'id')))
            ->toHaveCount(4)
            ->and(array_column($second['data'], 'full_name'))->toContain('Late arrival');
    });

    test('a full row carries contact details', function () {
        dataApiLead('2026-09-01 12:00:00', ['email' => 'jane@example.com', 'utm_source' => 'google']);

        $row = (new LeadDataFeed)->leads(dataApiWindow('2026-09-01', '2026-09-02'), LeadExportColumns::groupSlugs())['data'][0];

        expect($row['email'])->toBe('jane@example.com')
            ->and($row['phone'])->toStartWith('+1305555')
            ->and($row['utm_source'])->toBe('google')
            ->and($row['possible_va'])->toBe('no');
    });

    test('an empty field is null, not an empty string', function () {
        dataApiLead('2026-09-01 12:00:00', ['company' => '']);

        $row = (new LeadDataFeed)->leads(dataApiWindow('2026-09-01', '2026-09-02'), ['contact'])['data'][0];

        expect($row)->toHaveKey('company')
            ->and($row['company'])->toBeNull();
    });

    test('a soft-deleted lead is left out of the rows and the total', function () {
        dataApiLead('2026-09-01 12:00:00');
        dataApiLead('2026-09-01 13:00:00')->delete();

        $page = (new LeadDataFeed)->leads(dataApiWindow('2026-09-01', '2026-09-02'), []);

        expect($page['total'])->toBe(1)
            ->and($page['data'])->toHaveCount(1);
    });
});

describe('bookings', function () {
    test('one booking logged by three listeners is one row, at the earliest time', function () {
        $lead = dataApiLead('2026-09-01 12:00:00');
        dataApiBooking($lead, '2026-09-01 15:00:02', 'Referral');
        dataApiBooking($lead, '2026-09-01 15:00:00', 'Slack');
        dataApiBooking($lead, '2026-09-01 15:00:01', 'Webhook');

        $page = (new LeadDataFeed)->bookings(dataApiWindow('2026-09-01', '2026-09-02'), []);

        expect($page['total'])->toBe(1)
            ->and($page['data'][0]['booked_at_utc'])->toBe('2026-09-01 15:00:00')
            ->and($page['data'][0]['id'])->toBe((string) $lead->id);
    });

    test('a booking counts in the window it was made, not the one the lead was captured in', function () {
        $lead = dataApiLead('2026-08-28 12:00:00');
        dataApiBooking($lead, '2026-09-01 15:00:00');

        $feed = new LeadDataFeed;

        expect($feed->bookings(dataApiWindow('2026-09-01', '2026-09-02'), [])['total'])->toBe(1)
            ->and($feed->bookings(dataApiWindow('2026-08-28', '2026-08-29'), [])['total'])->toBe(0);
    });

    test('a rebooking does not count again after the first booking', function () {
        $lead = dataApiLead('2026-08-28 12:00:00');
        dataApiBooking($lead, '2026-08-29 15:00:00');
        dataApiBooking($lead, '2026-09-01 15:00:00');

        expect((new LeadDataFeed)->bookings(dataApiWindow('2026-09-01', '2026-09-02'), [])['total'])->toBe(0);
    });

    test('a booking on the boundary second belongs to the window it starts', function () {
        $lead = dataApiLead('2026-09-01 12:00:00');
        dataApiBooking($lead, '2026-09-02 04:00:00');

        $feed = new LeadDataFeed;

        expect($feed->bookings(dataApiWindow('2026-09-01', '2026-09-02'), [])['total'])->toBe(0)
            ->and($feed->bookings(dataApiWindow('2026-09-02', '2026-09-03'), [])['total'])->toBe(1);
    });

    test('a booked lead that has been deleted is missing from the total as well as the rows', function () {
        $kept = dataApiLead('2026-09-01 12:00:00');
        $gone = dataApiLead('2026-09-01 13:00:00');
        dataApiBooking($kept, '2026-09-01 15:00:00');
        dataApiBooking($gone, '2026-09-01 16:00:00');
        $gone->delete();

        $page = (new LeadDataFeed)->bookings(dataApiWindow('2026-09-01', '2026-09-02'), []);

        expect($page['total'])->toBe(1)
            ->and($page['data'])->toHaveCount(1);
    });

    test('the cost alert keeps its closed end', function () {
        $lead = dataApiLead('2026-09-01 12:00:00');
        dataApiBooking($lead, '2026-09-01 23:59:59');

        $from = CarbonImmutable::parse('2026-09-01 00:00:00', 'UTC');
        $to = CarbonImmutable::parse('2026-09-01 23:59:59', 'UTC');

        expect(LeadBookings::firstBookedBetween($from, $to))->toHaveKey($lead->id)
            ->and(LeadBookings::firstBookedBetween($from, $to, includeEnd: false))->toBe([]);
    });
});

describe('field names', function () {
    /*
     * The keys are the CSV headers slugged, so renaming a header in LeadExportColumns renames a
     * field in every warehouse table loaded from this API. That is allowed — but on purpose, with
     * the data team told, never as a side effect of tidying a label. Changing this list is the
     * signal that it is happening.
     */
    test('are a contract, and changing one is deliberate', function () {
        $keys = array_map([LeadExportColumns::class, 'key'], LeadExportColumns::headers(LeadExportColumns::groupSlugs()));

        expect($keys)->toBe([
            'id', 'uuid', 'created_date_utc', 'full_name', 'first_name', 'last_name', 'email', 'status', 'deleted_at_utc',
            'phone', 'phone_country', 'company', 'timezone',
            'role_needed', 'weekly_hours', 'monthly_revenue', 'start_date', 'notes', 'submission_type', 'data_source',
            'possible_va', 'audience_note',
            'scheduled_time', 'google_meet_url', 'scheduler_link', 'booking_retry_count',
            'source_type', 'source_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
            'landing_url', 'landing_page_base', 'referrer_url',
            'gclid', 'fbclid', 'linkedin_click_id', 'meta_fbc',
            'referral_code', 'partner', 'oppref',
            'session_id', 'posthog_session_id', 'posthog_replay_url', 'device_id', 'country', 'country_source',
            'ip_country', 'browser_timezone', 'browser_language', 'hubspot_contact_id', 'hubspot_lifecycle_stage',
            'hubspot_contact_url',
            'attribution_json',
            'activity_log_entries',
        ]);
    });

    test('never collide, so no header can overwrite another in a record', function () {
        $keys = array_map([LeadExportColumns::class, 'key'], LeadExportColumns::headers(LeadExportColumns::groupSlugs()));

        expect(array_unique($keys))->toHaveCount(count($keys));
    });
});

describe('LeadDataRestRoutes', function () {
    test('a caller without a credential is asked for one', function () {
        $GLOBALS['_wp_mock_capabilities'] = [];

        expect((new LeadDataRestRoutes(new LeadDataFeed))->authorize()->get_error_data()['status'])->toBe(401);
    });

    test('a signed-in user without the capability is told it is not enough', function () {
        $GLOBALS['_wp_mock_capabilities'] = ['read'];
        $GLOBALS['wp_current_user_logged_in'] = true;

        expect((new LeadDataRestRoutes(new LeadDataFeed))->authorize()->get_error_data()['status'])->toBe(403);
    });

    test('the insights capability is what lets a caller in', function () {
        $GLOBALS['_wp_mock_capabilities'] = [InsightsCapability::NAME];

        expect((new LeadDataRestRoutes(new LeadDataFeed))->authorize())->toBeTrue();
    });

    test('an unknown group is refused instead of silently returning fewer fields', function () {
        $response = (new LeadDataRestRoutes(new LeadDataFeed))
            ->handle('leads', ['from' => '2026-09-01', 'to' => '2026-09-02', 'groups' => 'contact,atribution']);

        expect($response)->toBeInstanceOf(WP_Error::class)
            ->and($response->get_error_code())->toBe('rl_data_unknown_group')
            ->and($response->get_error_message())->toContain('atribution');
    });

    test('a request is answered with how its window was read', function () {
        dataApiLead('2026-09-01 12:00:00');

        $response = (new LeadDataRestRoutes(new LeadDataFeed))
            ->handle('leads', ['from' => '2026-09-01', 'to' => '2026-09-02', 'groups' => 'attribution']);

        expect($response['window'])->toBe(['from' => '2026-09-01T04:00:00Z', 'to' => '2026-09-02T04:00:00Z'])
            ->and($response['groups'])->toBe(['identity', 'attribution'])
            ->and($response['data'][0])->toHaveKey('utm_source')
            ->and($response['data'][0])->not->toHaveKey('phone');
    });
});
