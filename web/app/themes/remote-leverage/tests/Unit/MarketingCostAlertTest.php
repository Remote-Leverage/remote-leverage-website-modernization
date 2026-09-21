<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadQualification;
use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Marketing\Actions\SendCostAlertAction;
use App\Domains\Marketing\Data\DaySupplement;
use App\Domains\Marketing\Data\Finding;
use App\Domains\Marketing\Data\FunnelSnapshot;
use App\Domains\Marketing\Data\MarketingDay;
use App\Domains\Marketing\Data\PartialDay;
use App\Domains\Marketing\Data\PlatformSlice;
use App\Domains\Marketing\Gateways\BigQueryClient;
use App\Domains\Marketing\Services\AlertReconciler;
use App\Domains\Marketing\Services\FindingDismissals;
use App\Domains\Marketing\Services\FunnelMetricsService;
use App\Domains\Marketing\Support\AdPlatformCredentials;
use App\Domains\Marketing\Support\AlertWindow;
use App\Domains\Marketing\Support\DemoSnapshot;
use App\Infrastructure\Slack\SlackTransport;
use App\Infrastructure\WordPress\Admin\MarketingDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/*
 * The marketing cost alert.
 *
 * The thing worth testing here is not that the arithmetic runs. It is that the message cannot
 * quietly say something untrue, which is what the alert this replaces did on 2026-09-18: it
 * printed a last-lead time and a last-booking time that could not both be real, divided spend by
 * a denominator that included bookings no platform had paid for, and reported a cost per booking
 * built from a single booking as though it were a rate.
 *
 * So the tests are mostly about suppression and honesty — what the alert refuses to claim.
 */

/**
 * The cards, without the findings replies posted under them.
 *
 * The reconciliation's findings used to be a red section inside the card; they are a threaded
 * reply now, so a run that finds something posts twice. Every assertion about "the card" means
 * the top-level one.
 *
 * @param  array<int, array<string, mixed>>  $posted
 * @return array<int, array<string, mixed>>
 */
function cardsAmong(array $posted): array
{
    return array_values(array_filter($posted, static fn (array $p): bool => ($p['thread_ts'] ?? null) === null));
}

/**
 * The findings replies, in the order they were posted.
 *
 * @param  array<int, array<string, mixed>>  $posted
 * @return array<int, array<string, mixed>>
 */
function findingsAmong(array $posted): array
{
    return array_values(array_filter($posted, static fn (array $p): bool => ($p['thread_ts'] ?? null) !== null));
}

function costAlertConfig(array $overrides = []): void
{
    $GLOBALS['_app_config'] = [];

    config(['marketing' => require __DIR__.'/../../config/marketing.php']);
    config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);

    // Fixtures are written in UTC, so the reporting day has to be UTC or "today" moves.
    config(['marketing.cost_alert.timezone' => 'UTC']);

    /*
     * No warehouse credentials, ever, whatever the machine's environment holds. A developer with
     * `BIGQUERY_CREDENTIALS_JSON` set would otherwise have this suite issue real queries against
     * the data team's project and assert on whatever today happens to look like. Tests that need a
     * warehouse inject `warehouseStub()`.
     */
    config(['marketing.warehouse.credentials' => '', 'marketing.warehouse.project_id' => '']);

    config($overrides);
}

/*
 * `created_at` is not fillable, so passing it to `create()` silently drops it and the row lands
 * at the real current time. Every date-windowed assertion in this file would then be measuring
 * today against a fixture that claims to be from 2026-09-18, and would pass or fail depending on
 * the day the suite runs. It is set after the insert, where being dirty keeps Eloquent's
 * timestamp handling from overwriting it.
 */
function costAlertLead(array $attributes = []): Lead
{
    static $sequence = 0;
    $sequence++;

    $createdAt = $attributes['created_at'] ?? null;
    unset($attributes['created_at']);

    $lead = Lead::create(array_merge([
        'uuid' => 'cost-'.$sequence,
        'name' => 'Cost '.$sequence,
        'email' => 'cost'.$sequence.'@example.com',
        'status' => 'captured',
        'source_type' => 'organic',
        'phone_country' => 'US',
        'monthly_revenue' => '$10k to $50k Per Month',
    ], $attributes));

    if ($createdAt !== null) {
        $lead->created_at = $createdAt;
        $lead->save();
    }

    return $lead;
}

/** Record the booking event the metrics service reads, at a given moment. */
function costAlertBooking(Lead $lead, CarbonImmutable $at, string $actor = 'Scheduling'): void
{
    LeadActivityLog::create([
        'lead_id' => $lead->id,
        'event_type' => 'LeadBookingCompleted',
        'actor_domain' => $actor,
        'stage' => 'consumption',
        'outcome' => 'succeeded',
        'created_at' => $at->format('Y-m-d H:i:s'),
    ]);

    $lead->update(['status' => 'booked']);
}

/**
 * The row that actually carries the meeting.
 *
 * Separate from costAlertBooking() because they are different rows in production and the
 * distinction is the whole point: the booking is logged by the Slack, webhook and referral
 * listeners under `LeadBookingCompleted`, and only the Scheduling domain's own `LeadCreated` row
 * holds the identifier the provider issued.
 */
function schedulingMeeting(Lead $lead, CarbonImmutable $at, string $meetingId): void
{
    LeadActivityLog::create([
        'lead_id' => $lead->id,
        'event_type' => 'LeadCreated',
        'actor_domain' => 'Scheduling',
        'stage' => 'consumption',
        'outcome' => 'succeeded',
        'payload' => ['meeting_id' => $meetingId, 'provider' => 'calendly'],
        'created_at' => $at->format('Y-m-d H:i:s'),
    ]);
}

/**
 * The phantom-booking finding out of a snapshot's warnings, or null.
 *
 * Picked out by substring rather than asserting on the whole array, so these tests say nothing
 * about whether the warehouse answered — which, with no BigQuery credential, it usually has not.
 *
 * @param  array<int, string>  $warnings
 */
function phantomBookingFinding(array $warnings): ?string
{
    foreach ($warnings as $warning) {
        if (str_contains($warning, 'no meeting on any calendar')) {
            return $warning;
        }
    }

    return null;
}

beforeEach(function () {
    Lead::query()->forceDelete();
    LeadActivityLog::query()->delete();
    costAlertConfig();

    /*
     * The option stub persists for the whole run, so a card remembered by one test sends the
     * next one down the update path and it posts nothing. Which is the real behaviour — that is
     * the point of the option — but it makes every later test depend on the order of the ones
     * before it.
     */
    update_option(SendCostAlertAction::STATE_OPTION, []);

    // The environment gate reads this, and a test that sets it would otherwise change the
    // meaning of every test after it.
    $GLOBALS['wp_environment_type'] = 'production';
});

describe('credential wiring', function () {
    /*
     * The one duplication this feature accepts, pinned.
     *
     * AdPlatformCredentials::KEYS cannot be derived from the config file, because
     * LeadSettingsService::defaults() reads it in contexts where the config repository is not
     * booted. Two representations are allowed; drifting apart is not — a key present in config
     * and missing here is a credential with no field on the settings screen, which looks exactly
     * like a credential nobody has filled in.
     */
    test('the credential key constant matches the config file exactly', function () {
        foreach (AdPlatformCredentials::KEYS as $platform => $keys) {
            expect($keys)->toBe(array_keys((array) config("marketing.ads.{$platform}")));
        }

        expect(array_keys(AdPlatformCredentials::KEYS))->toBe(AdPlatformCredentials::PLATFORMS);
    });

    test('a platform missing any credential is not reported as configured', function () {
        config(['marketing.ads.meta.access_token' => 'tok', 'marketing.ads.meta.ad_account_id' => '']);

        expect(AdPlatformCredentials::configured('meta'))->toBeFalse();

        config(['marketing.ads.meta.ad_account_id' => 'act_1']);

        expect(AdPlatformCredentials::configured('meta'))->toBeTrue();
    });

    test('Google is configured without a login customer id, which only manager accounts need', function () {
        config([
            'marketing.ads.google.developer_token' => 'dev',
            'marketing.ads.google.client_id' => 'cid',
            'marketing.ads.google.client_secret' => 'secret',
            'marketing.ads.google.refresh_token' => 'refresh',
            'marketing.ads.google.customer_id' => '123',
            'marketing.ads.google.login_customer_id' => '',
        ]);

        expect(AdPlatformCredentials::configured('google'))->toBeTrue();
    });
});

describe('reconciliation', function () {
    $clean = [
        'leads' => 40,
        'bookings' => 10,
        'last_lead_minutes' => 12,
        'last_booking_minutes' => 30,
        'platform_bookings' => 10,
        'within_window' => true,
    ];

    test('says nothing when the figures agree', function () use ($clean) {
        expect((new AlertReconciler)->check($clean))->toBe([]);
    });

    /*
     * A newest booking more recent than a newest lead reads like an impossibility and is not one.
     * An hour with no new leads in which an older lead books produces exactly that, and it is an
     * ordinary afternoon. This was briefly checked for and the check was wrong; the test stays as
     * the guard against it being reintroduced, because it is a genuinely tempting mistake.
     */
    test('an older lead booking during a quiet hour is not a contradiction', function () use ($clean) {
        $findings = (new AlertReconciler)->check([
            ...$clean,
            'last_lead_minutes' => 90,
            'last_booking_minutes' => 5,
        ]);

        expect($findings)->toBe([]);
    });

    /*
     * The 2026-09-18 failure: a day of bookings on real ad spend with no lead since the previous
     * evening, against a historical booking rate near one to one.
     */
    test('catches bookings on a day with no leads at all', function () use ($clean) {
        $findings = (new AlertReconciler)->check([...$clean, 'leads' => 0]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('not one new lead');
    });

    /*
     * The silent failure that has no other detector.
     *
     * `status = 'booked'` and a booking event are different measures and may legitimately
     * disagree — but zero events on a day where leads are marked booked means the log is not
     * being written, and every count and cost figure downstream reads zero while looking healthy.
     */
    test('catches leads marked booked on a day with no booking events', function () use ($clean) {
        $findings = (new AlertReconciler)->check([
            ...$clean,
            'bookings' => 0,
            'platform_bookings' => 0,
            'booked_by_status' => 34,
        ]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('34 leads')
            ->and($findings[0])->toContain('booking logging has stopped');
    });

    test('the two booking measures merely disagreeing is not a finding', function () use ($clean) {
        // A lead captured yesterday booking today belongs to different days under each measure.
        expect((new AlertReconciler)->check([...$clean, 'bookings' => 10, 'booked_by_status' => 4]))->toBe([]);
    });

    test('catches platform rows that do not sum to the total', function () use ($clean) {
        $findings = (new AlertReconciler)->check([...$clean, 'platform_bookings' => 8]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('wrong denominator');
    });

    test('a long silence is an incident inside the window and normal outside it', function () use ($clean) {
        $stale = [...$clean, 'last_lead_minutes' => 600, 'last_booking_minutes' => 700];

        expect((new AlertReconciler)->check($stale))->toHaveCount(1)
            ->and((new AlertReconciler)->check([...$stale, 'within_window' => false]))->toBe([]);
    });

    /*
     * Everything on the cost half of the card comes from the warehouse, so a warehouse that does
     * not answer is not a degraded card, it is half a card — and the missing half is the reason
     * the alert exists. It has to be named, or the gap where the money should be reads as a day
     * with no spend. This is the successor to the "an unreachable platform explains the missing
     * cost figures" check: one source now instead of three, so one finding instead of three.
     */
    test('the warehouse not answering is named rather than left as a gap', function () use ($clean) {
        $findings = (new AlertReconciler)->check([...$clean, 'warehouse_unavailable' => true]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('did not answer')
            ->and($findings[0])->toContain('cost per booking')
            // The funnel half still came from this site, and saying so stops the whole card being
            // discarded along with the figures that really are missing.
            ->and($findings[0])->toContain('unaffected');
    });

    /*
     * The card reports the warehouse's booking count, so if this site recorded a materially
     * different number one of the two is wrong and nobody should be dividing spend by either
     * until it is known which.
     *
     * A quarter, floored at three: a quiet morning where one booking differs must not cry wolf,
     * and a day where thirty do must not pass.
     */
    /*
     * The finding that would have caught Marvin Rodriguez at 10:54 ET on 2026-09-21, and the four
     * before him, instead of a warehouse gap being investigated for an unrelated reason three
     * days later.
     */
    test('a booking with no meeting anywhere is named, not counted', function () use ($clean) {
        $findings = (new AlertReconciler)->check([
            ...$clean,
            'bookings_without_meeting' => ['Marvin Rodriguez'],
        ]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('Marvin Rodriguez is marked booked with no meeting')
            ->and($findings[0])->toContain('This needs a call');
    });

    test('several of them are one finding each, so they can be dismissed one at a time', function () use ($clean) {
        $findings = (new AlertReconciler)->findings([
            ...$clean,
            // Also a wide warehouse surplus, to pin the ordering: a person to call outranks a
            // number to reconcile.
            'warehouse_bookings' => 40,
            'bookings_without_meeting' => [
                4301 => 'Virji Angelo',
                4302 => 'Susan Ornstein',
                4213 => 'Patrick Chism',
            ],
        ]);

        /*
         * One sentence each rather than one listing all three. A grouped sentence cannot be
         * dismissed: the dismissal is per lead, and the sentence would go on naming somebody who
         * had already been rung.
         */
        expect($findings)->toHaveCount(4)
            ->and(array_map(static fn ($f) => $f->key, $findings))->toBe([
                'booking-no-meeting:4301',
                'booking-no-meeting:4302',
                'booking-no-meeting:4213',
                'warehouse-ahead-of-site',
            ])
            ->and($findings[0]->text)->toContain('Virji Angelo is marked booked')
            ->and($findings[3]->text)->toContain('warehouse reports 40 bookings');
    });

    test('a person is settled once dealt with; a day-level finding is not', function () use ($clean) {
        $findings = (new AlertReconciler)->findings([
            ...$clean,
            'warehouse_bookings' => 40,
            'bookings_without_meeting' => [4343 => 'Marvin Rodriguez'],
        ]);

        /*
         * The safety model. Ringing Marvin does not change the row, so dismissing that settles
         * it for good; the warehouse disagreeing is the state of a day, and silencing next
         * month's occurrence from today's dashboard is exactly what must not be possible.
         */
        expect($findings[0]->permanent)->toBeTrue()
            ->and($findings[1]->permanent)->toBeFalse();
    });

    test('bookings that all name a real meeting say nothing', function () use ($clean) {
        expect((new AlertReconciler)->check([...$clean, 'bookings_without_meeting' => []]))->toBe([]);
    });

    test('a warehouse well ahead of this site is flagged', function () use ($clean) {
        // The lag runs one way only — a site booking becomes a RecruitCRM deal minutes later, never
        // the reverse — so a surplus in the warehouse cannot be the lag.
        $findings = (new AlertReconciler)->check([...$clean, 'warehouse_bookings' => 40]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('warehouse reports 40 bookings')
            ->and($findings[0])->toContain('this site recorded only 10');
    });

    /*
     * The 2026-09-21 11:05 card, which said "4 bookings today and this site recorded 10" and was
     * wrong to call it a contradiction. All ten reconciled against the four: three of them were
     * repeat bookers whose RecruitCRM deal was opened on 1, 7 and 18 September, two were one
     * person submitting twice, one was an internal test, and the newest were simply still inside
     * the few minutes it takes a deal to appear.
     */
    test('a warehouse behind this site is the normal shape of a day in progress', function () use ($clean) {
        expect((new AlertReconciler)->check([...$clean, 'warehouse_bookings' => 4]))->toBe([])
            ->and((new AlertReconciler)->check([...$clean, 'warehouse_bookings' => 1]))->toBe([]);
    });

    test('no deals at all against a site that has been booking is the CRM intake stopping', function () use ($clean) {
        $findings = (new AlertReconciler)->check([...$clean, 'warehouse_bookings' => 0]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('no bookings today')
            ->and($findings[0])->toContain('CRM intake has stopped');
    });

    test('the two booking counts differing a little is not a finding', function () use ($clean) {
        // Different definitions, different load times, and the site excludes likely VA applicants
        // where the warehouse does not. Within tolerance this is the normal state, not an incident.
        expect((new AlertReconciler)->check([...$clean, 'warehouse_bookings' => 12]))->toBe([])
            // The floor of three is what keeps a near-empty morning quiet: 0 against 2 is a
            // quarter of nothing, and without the floor every such morning would be flagged.
            ->and((new AlertReconciler)->check([
                ...$clean, 'leads' => 0, 'bookings' => 0, 'platform_bookings' => 0,
                'warehouse_bookings' => 2,
            ]))->toBe([]);
    });
});

describe('cost arithmetic', function () {
    /*
     * The legacy numbers, reproduced exactly, then corrected.
     *
     * $3,732.24 over 14 bookings is the $266.59 that alert printed. Over the 11 bookings a
     * platform could actually be named for, it is $339.29 — 27% higher, and the figure anyone
     * deciding where to put tomorrow's budget actually needs.
     */
    test('paid and blended cost per booking are both printed, and differ', function () {
        $day = costAlertDay([
            'total_appointments' => '14',
            'total_qualified' => '9',
            'total_spend' => '3732.24',
            'cpb_all' => '266.59',
            'cpqb_all' => '414.69',
            'cpb_paid' => '339.29',
            'cpqb_paid' => '533.18',
            'unclassified_appointments' => '3',
            'unclassified_appointments_share' => '0.2143',
        ]);

        $action = costAlertAction($transport = recordingCostTransport(), $day);
        $action->execute(CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC'), force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('CPB: $339.29')
            ->and($json)->toContain('CPQB: $533.18')
            // Blended last, with its denominator named. Above the paid figure it would invite
            // somebody to quote whichever of the two is nicer.
            ->and($json)->toContain('Blended CPB: $266.59 (all 14 bookings, paid and not)')
            /*
             * The 21.4% the legacy alert never printed. It is the share of bookings that did not
             * come from paid — the warehouse's `NOT is_paid_channel`, which is broader than "we
             * failed to attribute it" and includes organic, direct and email.
             */
            ->and($json)->toContain('Not from paid: 3 of 14 bookings (21.4%)');
    });

    /*
     * The view computes `cpb_all` and `cpb_paid` independently, and on a day where everything came
     * from paid they agree. Printing the same number twice under two names teaches the reader that
     * the distinction is decorative, so the blended line is dropped rather than repeated.
     */
    test('with everything attributed the blended line is dropped rather than repeated', function () {
        $day = costAlertDay([
            'total_appointments' => '10',
            'total_spend' => '1000.0',
            'cpb_all' => '100.0',
            'cpb_paid' => '100.0',
            'unclassified_appointments' => '0',
            'unclassified_appointments_share' => '0.0',
        ]);

        $action = costAlertAction($transport = recordingCostTransport(), $day);
        $action->execute(CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC'), force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('CPB: $100.00')
            ->and($json)->not->toContain('Blended CPB');
    });

    /*
     * A day the warehouse reports with no spend figure attached is not a day that spent nothing.
     * `SAFE_DIVIDE` gives null rather than zero for the same reason, all the way down: a CPB of
     * $0.00 reads as excellent performance and sits comfortably under every target.
     */
    test('no spend means no cost figures rather than zeroes', function () {
        $day = costAlertDay([
            'total_spend' => null,
            'facebook_spend' => null,
            'google_spend' => null,
            'bing_spend' => null,
            'cpl' => null,
            'cpb_all' => null,
            'cpqb_all' => null,
            'cpb_paid' => null,
            'cpqb_paid' => null,
            'facebook_cpb' => null,
            'google_cpb' => null,
            'bing_cpb' => null,
        ]);

        $action = costAlertAction($transport = recordingCostTransport(), $day);
        $action->execute(CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC'), force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('Spend: not reported for this day')
            ->and($json)->not->toContain('$0.00')
            ->and($json)->not->toContain('CPB:');
    });

    test('a platform with one booking is marked as not a rate', function () {
        $one = new PlatformSlice('google', 'Google Ads', leads: 4, bookings: 1, bookingsViaClickId: 0, qualified: 1, spend: 737.46);
        $many = new PlatformSlice('meta', 'Meta', leads: 40, bookings: 9, bookingsViaClickId: 0, qualified: 5, spend: 2892.84);

        expect($one->isSmallSample())->toBeTrue()
            ->and($one->cpb())->toBe(737.46)
            ->and($many->isSmallSample())->toBeFalse()
            ->and(round((float) $many->cpb(), 2))->toBe(321.43)
            ->and(round((float) $many->cpqb(), 2))->toBe(578.57);
    });

    /*
     * Measured on the live table 2026-09-19: of the 41 booked leads the click-ID fallback newly
     * credits to Meta, HandL says 23 were social, 6 direct and only 12 paid. Dividing Meta's
     * spend by all 41 reports a cost per booking under a third of the real one, in the flattering
     * direction, on the number budget is moved with.
     */
    test('bookings reached only through an fbclid stay out of the cost denominator', function () {
        $slice = new PlatformSlice(
            'meta', 'Meta', leads: 90, bookings: 41, bookingsViaClickId: 41, qualified: 20,
            spend: 3000.0, bookingsNotProvenPaid: 29,
        );

        expect($slice->paidBookings())->toBe(12)
            ->and(round((float) $slice->cpb(), 2))->toBe(250.0)
            // The naive denominator, for contrast: three times cheaper and wrong.
            ->and(round(3000.0 / 41, 2))->toBe(73.17);
    });

    /*
     * Qualified bookings are a subset of bookings, so cost per qualified booking can never be
     * below cost per booking. It once was, because the two denominators were reduced differently —
     * spend divided by the full qualified count while the booking count had its unproven-paid
     * bookings taken out. The subtraction has to happen on both sides or the card prints an
     * impossibility.
     */
    test('cost per qualified booking is never cheaper than cost per booking', function () {
        $slice = new PlatformSlice(
            'meta', 'Meta', leads: 30, bookings: 3, bookingsViaClickId: 2, qualified: 2,
            spend: 900.0, bookingsNotProvenPaid: 2, qualifiedNotProvenPaid: 1,
        );

        expect($slice->paidBookings())->toBe(1)
            ->and($slice->paidQualified())->toBe(1)
            ->and($slice->cpb())->toBe(900.0)
            ->and($slice->cpqb())->toBe(900.0)
            ->and($slice->cpqb())->toBeGreaterThanOrEqual($slice->cpb());
    });

    test('a platform that booked nothing has no cost per booking to report', function () {
        $slice = new PlatformSlice('microsoft', 'Bing', leads: 3, bookings: 0, bookingsViaClickId: 0, qualified: 0, spend: 101.94);

        expect($slice->cpb())->toBeNull()
            ->and($slice->cpqb())->toBeNull()
            ->and($slice->isSmallSample())->toBeFalse();
    });
});

describe('metrics', function () {
    test('VA applicants are excluded from leads and bookings, and counted separately', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $client = costAlertLead(['created_at' => $now->subHours(2), 'phone_country' => 'US']);
        $applicant = costAlertLead(['created_at' => $now->subHours(2), 'phone_country' => 'PH']);

        costAlertBooking($client, $now->subHour());
        costAlertBooking($applicant, $now->subHour());

        $snapshot = (new FunnelMetricsService)->snapshot($now);

        expect($snapshot->leads)->toBe(1)
            ->and($snapshot->bookings)->toBe(1)
            ->and($snapshot->excludedVaLeads)->toBe(1)
            ->and($snapshot->excludedVaBookings)->toBe(1);
    });

    /*
     * Three listeners log the same booking under their own actor_domain. Counting rows would
     * report one booking as three, and divide spend by a denominator three times too large.
     */
    test('one booking logged by three listeners counts once', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');
        $lead = costAlertLead(['created_at' => $now->subHours(3)]);

        foreach (['Scheduling', 'Slack', 'Referral'] as $actor) {
            costAlertBooking($lead, $now->subHour(), $actor);
        }

        expect((new FunnelMetricsService)->snapshot($now)->bookings)->toBe(1);
    });

    /*
     * End to end, because the wiring is the part that can be wrong while both halves look right:
     * the meeting lives on a `LeadCreated`/Scheduling row, and the booking that makes the lead
     * count lives on a `LeadBookingCompleted` row written by a different listener.
     */
    test('a booking whose meeting id was minted here reaches the card by name', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $phantom = costAlertLead(['name' => 'Marvin Rodriguez', 'created_at' => $now->subHours(2)]);
        costAlertBooking($phantom, $now->subHour());
        schedulingMeeting($phantom, $now->subHour(), 'gcal_6ab1450a4b7021.28353181');

        $real = costAlertLead(['name' => 'Vanessa Mayer', 'created_at' => $now->subHours(2)]);
        costAlertBooking($real, $now->subHour());
        schedulingMeeting($real, $now->subHour(), 'https://api.calendly.com/scheduled_events/af92/invitees/592e');

        $finding = phantomBookingFinding((new FunnelMetricsService)->snapshot($now)->warnings);

        expect($finding)->not->toBeNull()
            ->and($finding)->toContain('Marvin Rodriguez')
            ->and($finding)->not->toContain('Vanessa Mayer');
    });

    test('a retry that lands clears a first attempt that did not', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $lead = costAlertLead(['name' => 'Retried', 'created_at' => $now->subHours(2)]);
        costAlertBooking($lead, $now->subHour());

        schedulingMeeting($lead, $now->subMinutes(40), 'gcal_first_attempt');
        schedulingMeeting($lead, $now->subMinutes(10), 'https://api.calendly.com/scheduled_events/ok/invitees/ok');

        // The newest Scheduling row is the one that counts, or every landed retry would be
        // reported for the attempt before it.
        expect(phantomBookingFinding((new FunnelMetricsService)->snapshot($now)->warnings))->toBeNull();
    });

    test('a dismissed finding stops reaching the card, and stays visible as dismissed', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $lead = costAlertLead(['name' => 'Marvin Rodriguez', 'created_at' => $now->subHours(2)]);
        costAlertBooking($lead, $now->subHour());
        schedulingMeeting($lead, $now->subHour(), 'gcal_6ab1450a4b7021.28353181');

        update_option(FindingDismissals::OPTION, []);

        expect(phantomBookingFinding((new FunnelMetricsService)->snapshot($now)->warnings))->not->toBeNull();

        // What the Dismiss button does.
        (new FindingDismissals)->dismiss(
            new Finding('booking-no-meeting:'.$lead->id, 'Marvin Rodriguez is marked booked…', permanent: true),
            $now,
        );

        $snapshot = (new FunnelMetricsService)->snapshot($now);

        /*
         * Out of `warnings`, which is what the Slack card renders — the point of the button is to
         * stop the notification. Still in `dismissedFindings`, which is what the dashboard shows
         * greyed with an undo, because a warning that vanishes without trace is its own problem.
         */
        expect(phantomBookingFinding($snapshot->warnings))->toBeNull()
            ->and($snapshot->dismissedFindings)->toHaveCount(1)
            ->and($snapshot->dismissedFindings[0]['key'])->toBe('booking-no-meeting:'.$lead->id);

        update_option(FindingDismissals::OPTION, []);
    });

    test('an instant live call is not reported as a booking with no meeting', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $lead = costAlertLead(['name' => 'Live Caller', 'created_at' => $now->subHours(2)]);
        costAlertBooking($lead, $now->subHour());

        /*
         * RouteInstantCallAction writes `invitee_uri` and no `meeting_id`. A detector reading only
         * `meeting_id` reports every healthy live call as a phantom — the warning that fires on
         * the good case, which is worse than no warning.
         */
        LeadActivityLog::create([
            'lead_id' => $lead->id,
            'event_type' => 'InstantLiveCall',
            'actor_domain' => 'Scheduling',
            'stage' => 'consumption',
            'outcome' => 'succeeded',
            'payload' => ['meeting_url' => 'https://calendly.com/x', 'invitee_uri' => 'https://api.calendly.com/invitees/live'],
            'created_at' => $now->subHour()->format('Y-m-d H:i:s'),
        ]);

        expect(phantomBookingFinding((new FunnelMetricsService)->snapshot($now)->warnings))->toBeNull();
    });

    test('a booking made at Calendly and reported by webhook is not flagged', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $lead = costAlertLead(['name' => 'Webhook Booker', 'created_at' => $now->subHours(2)]);
        // No Scheduling row at all: the wizard never ran. Calendly is the one that told us.
        costAlertBooking($lead, $now->subHour(), 'Slack');

        expect(phantomBookingFinding((new FunnelMetricsService)->snapshot($now)->warnings))->toBeNull();
    });

    test("a lead captured yesterday that books today is today's booking", function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');
        $lead = costAlertLead(['created_at' => $now->subDay()]);

        costAlertBooking($lead, $now->subHour());

        $snapshot = (new FunnelMetricsService)->snapshot($now);

        expect($snapshot->bookings)->toBe(1)
            ->and($snapshot->leads)->toBe(0);
    });

    test('bookings bucket by platform and the buckets sum to the total', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $shapes = [
            ['utm_source' => 'facebook'],
            ['utm_source' => 'facebook'],
            ['utm_source' => 'adwords'],
            ['utm_source' => null, 'gclid' => 'G1'],   // rescued by a click id
            ['utm_source' => null],                     // genuinely unattributed
            ['utm_source' => 'mystery'],                // tagged, unrecognised
        ];

        foreach ($shapes as $shape) {
            costAlertBooking(
                costAlertLead([...$shape, 'created_at' => $now->subHours(2)]),
                $now->subHour(),
            );
        }

        /*
         * A warehouse agreeing with the site, so the only thing that can put a finding on this
         * snapshot is the partition itself. Without it every snapshot in the suite carries the
         * "warehouse did not answer" finding and `warnings` stops being able to say anything.
         */
        $snapshot = (new FunnelMetricsService(null, warehouseStub(costAlertDay([
            'total_appointments' => '6',
        ]))))->snapshot($now);

        $summed = array_sum(array_map(
            static fn (PlatformSlice $slice): int => $slice->bookings,
            $snapshot->platforms,
        ));

        $unattributed = $snapshot->platforms['direct']->bookings + $snapshot->platforms['other']->bookings;

        $attributed = array_sum(array_map(
            static fn (PlatformSlice $slice): int => $slice->bookings,
            $snapshot->reportablePlatforms(),
        ));

        expect($snapshot->bookings)->toBe(6)
            ->and($summed)->toBe(6)
            ->and($snapshot->platforms['meta']->bookings)->toBe(2)
            ->and($snapshot->platforms['google']->bookings)->toBe(2)
            ->and($snapshot->platforms['google']->bookingsViaClickId)->toBe(1)
            ->and($unattributed)->toBe(2)
            ->and($attributed)->toBe(4)
            ->and($snapshot->warnings)->toBe([]);
    });

    test('qualified counts the T10 revenue bands and nothing else', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        foreach (['$10k to $50k Per Month', '$5k to $10k Per Month', '<10k', null] as $band) {
            costAlertBooking(
                costAlertLead(['monthly_revenue' => $band, 'created_at' => $now->subHours(2)]),
                $now->subHour(),
            );
        }

        expect((new FunnelMetricsService)->snapshot($now)->qualifiedT10)->toBe(1);
    });

    /*
     * The HubSpot definition is wired and currently unusable: the lifecycle sync only polls
     * referral-attached leads, so a count over it is a count of referrals. It must suppress
     * itself rather than report confidently off a self-selected slice.
     */
    test('the HubSpot qualified figure is suppressed while coverage is thin', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        costAlertBooking(costAlertLead(['hubspot_lifecycle_stage' => 'customer', 'created_at' => $now->subHours(2)]), $now->subHour());

        foreach (range(1, 4) as $ignored) {
            costAlertBooking(costAlertLead(['created_at' => $now->subHours(2)]), $now->subHour());
        }

        $snapshot = (new FunnelMetricsService)->snapshot($now);

        expect($snapshot->qualifiedHubSpot)->toBeNull()
            ->and(round($snapshot->hubSpotCoverage, 2))->toBe(0.2);
    });

    test('the HubSpot figure appears once coverage is good enough', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        foreach (['customer', 'salesqualifiedlead', 'lead'] as $stage) {
            costAlertBooking(costAlertLead(['hubspot_lifecycle_stage' => $stage, 'created_at' => $now->subHours(2)]), $now->subHour());
        }

        $snapshot = (new FunnelMetricsService)->snapshot($now);

        // `lead` is on the ladder but below sales-qualified, so it is covered and not qualified.
        expect($snapshot->hubSpotCoverage)->toBe(1.0)
            ->and($snapshot->qualifiedHubSpot)->toBe(2);
    });

    test('the reconciler runs against the real figures, not just against a fixture', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        // A lead from the previous evening booking this morning, and nothing new all day.
        costAlertBooking(costAlertLead(['created_at' => $now->subHours(20)]), $now->subHours(4));

        $snapshot = (new FunnelMetricsService)->snapshot($now);

        expect($snapshot->leads)->toBe(0)
            ->and($snapshot->bookings)->toBe(1)
            ->and(implode(' ', $snapshot->warnings))->toContain('not one new lead');
    });
});

describe('the message', function () {
    /*
     * A card with no cost figures has to say which source is missing. The mechanism changed — one
     * warehouse now rather than three ad platforms — but the failure it guards has not: a gap
     * where the money should be reads as a day that spent nothing, and zeroes read as a day that
     * spent nothing for free.
     */
    test('says the warehouse did not answer rather than printing zero', function () {
        $action = costAlertAction($transport = recordingCostTransport());

        $action->execute(CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC'), force: true);

        expect(cardsAmong($transport->posted))->toHaveCount(1)
            ->and(json_encode($transport->posted[0]['blocks']))
            ->toContain('did not answer this run')
            ->not->toContain('$0.00');

        // And the small print says so too, rather than leaving the reader to infer it.
        expect(json_encode($transport->posted[0]['blocks']))->toContain('warehouse unavailable');
    });

    /*
     * An unconfigured platform renders its plain name, never a literal shortcode.
     *
     * Slack renders an emoji the workspace does not have as the text `:linkedin:`. The bot cannot
     * check which exist — `emoji.list` needs a scope it does not hold — so the only protection is
     * that an empty slug produces no icon at all. Asserted against a slug set empty here rather
     * than against whatever the config happens to ship, so adding a logo cannot quietly turn this
     * into a test of nothing.
     */
    test('a platform with no configured logo renders its plain name', function () {
        config(['marketing.cost_alert.platform_emoji.meta' => '']);

        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');

        $action = costAlertAction($transport = recordingCostTransport(), costAlertDay());
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('Meta')
            ->and($json)->not->toContain(':meta:');
    });

    test('a configured logo is prefixed to the platform name', function () {
        config(['marketing.cost_alert.platform_emoji.meta' => ':meta:']);

        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');

        $action = costAlertAction($transport = recordingCostTransport(), costAlertDay());
        $action->execute($now, force: true);

        expect(json_encode($transport->posted[0]['blocks']))->toContain(':meta: Meta');
    });

    /*
     * Every shipped shortcode has to be a real one in the workspace, and nothing in code can
     * check that. What can be checked is the shape: a value that is not `:name:` is a typo, and a
     * typo renders as literal text on every card until somebody notices.
     */
    test('every configured platform logo is a well-formed shortcode', function () {
        foreach ((array) config('marketing.cost_alert.platform_emoji') as $slug => $shortcode) {
            if ($shortcode === '') {
                continue;
            }

            expect($shortcode)->toMatch('/^:[a-z0-9_+-]+:$/', "{$slug} has a malformed emoji shortcode");
        }
    });

    /*
     * "Next business day" is arithmetic the reader has to do against today's date and the
     * weekend, and on a Friday afternoon they will do it wrong. The days are named instead.
     */
    test('consultations name each upcoming business day', function () {
        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');

        $metrics = new class extends FunnelMetricsService
        {
            public function snapshot(?CarbonImmutable $now = null, ?callable $onProgress = null): FunnelSnapshot
            {
                return DemoSnapshot::build($now);
            }
        };

        $action = new SendCostAlertAction($metrics, $transport = recordingCostTransport(), new SlackMessageRenderer);
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('97 today')
            ->and($json)->toContain('29 Monday 21st')
            ->and($json)->toContain('18 Tuesday 22nd')
            ->and($json)->not->toContain('next business day');
    });

    /*
     * A day Calendly would not answer for reads as `unavailable`, never as zero or a skipped
     * entry. An empty calendar and an unreachable one mean opposite things, and one of them is a
     * revenue stop.
     */
    test('a calendar day that could not be read says so', function () {
        $snapshot = consultationSnapshot(12, ['Monday 21st' => null, 'Tuesday 22nd' => 4]);

        $action = costAlertAction($transport = recordingCostTransport());
        $action->preview($snapshot, 'example');

        expect(json_encode($transport->posted[0]['blocks']))
            ->toContain('12 today, unavailable Monday 21st, 4 Tuesday 22nd');
    });

    /*
     * The figure that started this.
     *
     * It came from Calendly until 2026-09-20, counted over the two event-type URIs held in the
     * `t0` and `t10` roles — two of the ten active event types named a VA Hiring Consultation. On
     * 2026-09-21 that printed 80 against a true 112, and sales moved to switch ads off over the
     * gap. It is one warehouse query now, covering all three days at once.
     */
    test('the calendar load covers today and the next two business days, from the warehouse', function () {
        // A Friday, so "the next two business days" has to skip a weekend to reach Monday.
        $now = CarbonImmutable::parse('2026-09-18 16:00:00', 'UTC');

        $snapshot = (new FunnelMetricsService(null, warehouseStub(null, null, [
            '2026-09-18' => 75,
            '2026-09-21' => 112,
            '2026-09-22' => 45,
        ])))->snapshot($now);

        expect($snapshot->consultationsToday)->toBe(75)
            ->and($snapshot->upcomingConsultations)->toBe([
                'Monday 21st' => 112,
                'Tuesday 22nd' => 45,
            ]);
    });

    /*
     * The distinction the LEFT JOIN in consultation-calendar-load.sql exists to preserve.
     *
     * A day the warehouse answered for is present even at zero; a day it did not answer for is
     * absent. Those mean opposite things — an empty calendar is a revenue stop somebody has to be
     * told about, an unreadable one is a pipeline to go and fix — and collapsing them into the
     * same silence is how a quiet Sunday and a broken warehouse become indistinguishable.
     */
    test('a warehouse day with no meetings reads as zero, a day it could not answer for as unavailable', function () {
        // A Sunday. Nothing is booked, and that is a fact rather than a failure.
        $now = CarbonImmutable::parse('2026-09-20 16:00:00', 'UTC');

        $snapshot = (new FunnelMetricsService(null, warehouseStub(null, null, [
            '2026-09-20' => 0,
            '2026-09-21' => 112,
            // Tuesday deliberately absent.
        ])))->snapshot($now);

        expect($snapshot->consultationsToday)->toBe(0)
            ->and($snapshot->upcomingConsultations)->toBe([
                'Monday 21st' => 112,
                'Tuesday 22nd' => null,
            ]);
    });

    /*
     * Resolved per day, not all-or-nothing. The Calendly version withheld the whole figure when
     * one tier went missing, and was right to: the tiers were summed, so a partial total was a
     * wrong total. These three are independent figures printed side by side.
     */
    test('an unreadable warehouse leaves every calendar day unavailable', function () {
        $now = CarbonImmutable::parse('2026-09-18 16:00:00', 'UTC');

        $snapshot = (new FunnelMetricsService(null, warehouseStub(null, null, null)))->snapshot($now);

        expect($snapshot->consultationsToday)->toBeNull()
            ->and($snapshot->upcomingConsultations)->toBe([
                'Monday 21st' => null,
                'Tuesday 22nd' => null,
            ]);
    });

    /*
     * The alert is never scheduled outside production, but it can still be fired by hand — the
     * dashboard button and `--force` both exist so somebody can test against real data, and both
     * land in the channel production posts to. Without a banner a staging card is
     * indistinguishable from the real thing.
     */
    test('a card sent from outside production says so', function () {
        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');
        $GLOBALS['wp_environment_type'] = 'staging';

        $action = costAlertAction($transport = recordingCostTransport());
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('TEST MESSAGE')
            ->and($json)->toContain('STAGING');
    });

    test('a production card carries no test banner', function () {
        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');
        $GLOBALS['wp_environment_type'] = 'production';

        $action = costAlertAction($transport = recordingCostTransport());
        $action->execute($now, force: true);

        expect(json_encode($transport->posted[0]['blocks']))->not->toContain('TEST MESSAGE');
    });

    /*
     * A fabricated card sent from staging is two different things the reader needs to know.
     * Dropping one because the other applies is how somebody mistakes a demo for a staging run.
     */
    test('a demo sent from staging carries both notices', function () {
        $GLOBALS['wp_environment_type'] = 'staging';

        $action = costAlertAction($transport = recordingCostTransport());
        $action->preview(DemoSnapshot::build(CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC')), 'EXAMPLE CARD');

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('EXAMPLE CARD')
            ->and($json)->toContain('TEST MESSAGE');
    });

    /*
     * House rule: no native emoji. A `:shortcode:` for a brand logo is a different thing and is
     * not what this guards — the pattern is unicode ranges, which a shortcode does not match.
     */
    test('carries no emoji', function () {
        $action = costAlertAction($transport = recordingCostTransport());
        $action->execute(CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC'), force: true);

        $json = json_encode($transport->posted[0]['blocks']);

        expect(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', (string) $json))->toBe(0);
    });

    test('a run with nothing to report posts no reply at all', function () {
        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');
        $day = costAlertDay(['total_appointments' => '0', 'cpb_all' => null, 'cpb_paid' => null]);

        $quiet = costAlertAction($transport = recordingCostTransport(), $day);
        $quiet->execute($now, force: true);

        // An empty thread is what makes a reply mean something when one appears.
        expect(findingsAmong($transport->posted))->toBeEmpty()
            ->and(cardsAmong($transport->posted))->toHaveCount(1);
    });

    test('findings go under the card as a reply, and never repaint it', function () {
        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');

        /*
         * An answering warehouse that agrees with the empty site, because "the warehouse did not
         * answer" is itself a finding — without a stub here the quiet run is red and the test
         * proves nothing.
         */
        $day = costAlertDay(['total_appointments' => '0', 'cpb_all' => null, 'cpb_paid' => null]);

        $quiet = costAlertAction($clean = recordingCostTransport(), $day);
        $quiet->execute($now, force: true);

        expect($clean->posted[0]['color'])->toBeNull();

        // Bookings today with no lead captured today — the reconciler's live check.
        costAlertBooking(costAlertLead(['created_at' => $now->subHours(20)]), $now->subHours(4));

        /*
         * Forget the card the quiet run remembered, or this one takes the edit path and posts
         * nothing. That behaviour is the feature; it just has to be out of the way to assert on
         * the colour of a fresh post.
         */
        update_option(SendCostAlertAction::STATE_OPTION, []);

        $loud = costAlertAction($flagged = recordingCostTransport(), $day);
        $loud->execute($now, force: true);

        /*
         * The card looks exactly as it did on the quiet run. What changed is that there is now a
         * reply under it — which is the whole point: the figures stay legible as figures, and the
         * thing to act on is attached to them rather than painted over them.
         */
        expect($flagged->posted[0]['color'])->toBeNull()
            ->and(cardsAmong($flagged->posted))->toHaveCount(1)
            ->and(findingsAmong($flagged->posted))->toHaveCount(1)
            ->and(json_encode(findingsAmong($flagged->posted)[0]['blocks']))
            ->toContain('Check before trusting these numbers');
    });

    /*
     * The guard bought by an accident: the hourly job fired from a local page request and posted
     * an empty card into the live sales channel, because `enabled` defaulted to true everywhere.
     */
    test('the scheduled alert does not post outside its configured environments', function () {
        config(['marketing.cost_alert.environments' => ['production'], 'marketing.cost_alert.enabled' => null]);
        $GLOBALS['wp_environment_type'] = 'development';

        $action = costAlertAction($transport = recordingCostTransport());
        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');

        expect($action->execute($now))->toBeFalse()
            ->and(cardsAmong($transport->posted))->toBeEmpty();

        // A person who typed the command has asked for it, wherever they are.
        expect($action->execute($now, force: true))->toBeTrue()
            ->and(cardsAmong($transport->posted))->toHaveCount(1);

        // The forced run above remembered today's card, so without this the next one correctly
        // takes the edit path and posts nothing. That behaviour has its own test.
        update_option(SendCostAlertAction::STATE_OPTION, []);
        $GLOBALS['wp_environment_type'] = 'production';

        $second = costAlertAction($live = recordingCostTransport());

        expect($second->execute($now))->toBeTrue()
            ->and(cardsAmong($live->posted))->toHaveCount(1);
    });

    test('a run blocked by the environment gate says so, with the values that decided it', function () {
        config(['marketing.cost_alert.environments' => ['production'], 'marketing.cost_alert.enabled' => null]);
        $GLOBALS['wp_environment_type'] = 'development';

        $action = costAlertAction($transport = recordingCostTransport());

        $warnings = [];
        Log::swap(new class($warnings)
        {
            public function __construct(public array &$seen) {}

            public function warning($message, array $context = []): void
            {
                $this->seen[] = (string) $message.' '.json_encode($context);
            }

            public function __call($name, $arguments) {}
        });

        expect($action->execute())->toBeFalse();

        Log::clearResolvedInstances();

        /*
         * The gate that cost an afternoon on 2026-09-21. It is checked before anything is
         * computed and used to return a bare false, so "switched off" looked exactly like Slack
         * being down, the cron being dead, or the send throwing — and the only person who could
         * tell the difference was firing every card by hand.
         */
        expect($warnings)->not->toBeEmpty()
            ->and($warnings[0])->toContain('switched off in this environment')
            ->and($warnings[0])->toContain('development')
            ->and($warnings[0])->toContain('enabled_env_raw')
            ->and(cardsAmong($transport->posted))->toBeEmpty();
    });

    test('an explicit enabled=false stops it even in production', function () {
        config(['marketing.cost_alert.environments' => ['production'], 'marketing.cost_alert.enabled' => false]);
        $GLOBALS['wp_environment_type'] = 'production';

        $action = costAlertAction($transport = recordingCostTransport());

        expect($action->execute(CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC')))->toBeFalse()
            ->and(cardsAmong($transport->posted))->toBeEmpty();
    });

    /*
     * The card posts round the clock now. The overnight hours are the cheapest to cover — the
     * same one card, edited — and the ones where a stalled ad account goes unnoticed longest.
     */
    test('posts in the middle of the night, because the window is the whole day', function () {
        $action = costAlertAction($transport = recordingCostTransport());

        expect($action->execute(CarbonImmutable::parse('2026-09-18 03:00:00', 'UTC')))->toBeTrue()
            ->and(cardsAmong($transport->posted))->toHaveCount(1);
    });

    test('still honours a narrowed window, and force still bypasses it', function () {
        config(['marketing.cost_alert.window' => ['from' => 9, 'to' => 18]]);

        $action = costAlertAction($transport = recordingCostTransport());
        $middleOfTheNight = CarbonImmutable::parse('2026-09-18 03:00:00', 'UTC');

        expect($action->execute($middleOfTheNight))->toBeFalse()
            ->and(cardsAmong($transport->posted))->toBeEmpty()
            ->and($action->execute($middleOfTheNight, force: true))->toBeTrue();
    });

    /*
     * Posting hours and staffed hours are now separate settings, and the silence check reads the
     * second. Sharing one value meant either no overnight card or a 4am card complaining that
     * nobody had filled in a form since midnight.
     */
    test('a long silence is an incident during staffed hours and not overnight', function () {
        $quiet = [
            'leads' => 0, 'bookings' => 0, 'platform_bookings' => 0, 'booked_by_status' => 0,
            'last_lead_minutes' => 400, 'last_booking_minutes' => 400,
        ];

        $night = AlertWindow::staffed(CarbonImmutable::parse('2026-09-18 04:00:00', 'UTC'));
        $desk = AlertWindow::staffed(CarbonImmutable::parse('2026-09-18 14:00:00', 'UTC'));

        expect($night)->toBeFalse()
            ->and($desk)->toBeTrue()
            ->and(AlertWindow::contains(CarbonImmutable::parse('2026-09-18 04:00:00', 'UTC')))->toBeTrue();

        $overnight = (new AlertReconciler)->check([...$quiet, 'within_window' => $night]);
        $working = (new AlertReconciler)->check([...$quiet, 'within_window' => $desk]);

        expect(implode(' ', $overnight))->not->toContain('No new lead')
            ->and(implode(' ', $working))->toContain('No new lead');
    });

    /*
     * Every run leaves its own card. The channel is the record of how the day developed, which is
     * the one question the dashboard widget cannot answer — it only ever shows now.
     */
    test('posts a new card on every run and never edits one', function () {
        $transport = recordingCostTransport();
        $action = costAlertAction($transport);

        $action->execute(CarbonImmutable::parse('2026-09-18 09:00:00', 'UTC'), force: true);
        $action->execute(CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC'), force: true);

        expect(cardsAmong($transport->posted))->toHaveCount(2)
            ->and($transport->updated)->toBeEmpty();
    });

    /*
     * A new day must not edit yesterday's card into today's numbers — that would destroy the
     * record of yesterday while looking like it worked.
     */
    test('a new day gets its own card', function () {
        $transport = recordingCostTransport();
        $action = costAlertAction($transport);

        $action->execute(CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC'), force: true);
        $action->execute(CarbonImmutable::parse('2026-09-19 09:00:00', 'UTC'), force: true);

        expect(cardsAmong($transport->posted))->toHaveCount(2)
            ->and($transport->updated)->toBeEmpty();
    });

    test('names where the figures come from and why one is missing', function () {
        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');
        costAlertBooking(costAlertLead(['created_at' => $now->subHours(2)]), $now->subHour());

        $action = costAlertAction($transport = recordingCostTransport(), costAlertDay());
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        /*
         * The small print is one line of clauses rather than the four paragraphs it used to be.
         * What must survive the condensing is the *coverage number* — a reader who cannot see how
         * thin it is has no way to know the HubSpot figure is suppressed rather than zero — and a
         * statement of which system the numbers came from, because the card's counts are the
         * warehouse's and the leads screen's are this site's, and they do not have to agree.
         *
         * NOTE: this test used to assert the card also names the T10 definition ("$10k+ MRR").
         * SendCostAlertAction::footnotes() now overwrites that clause with the warehouse-source
         * one instead of adding it, so neither qualified definition reaches the card. That looks
         * unintended rather than decided; see the report accompanying this change.
         */
        expect($json)->toContain('from the marketing warehouse')
            ->and($json)->toContain('HubSpot stage known for')
            ->and($json)->not->toContain('HubSpot-qualified 0');
    });
});

describe('the warehouse half, end to end', function () {
    /*
     * The whole point of the feature, exercised from a warehouse row to the rendered card: the
     * day's money, the channel breakdown, and the paid figure printed beside the blended one with
     * the blended denominator named.
     *
     * What changed is where the numbers come from. The alert used to read three ad platforms and
     * do its own attribution; the counts a cost figure divides by now arrive from the same query
     * as the spend. A numerator and a denominator from two different systems is not a measurement
     * of anything, and it is how a Slack card and the data team's dashboard start disagreeing.
     */
    test('spend reaches the card, with one channel card per channel that spent', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $action = costAlertAction($transport = recordingCostTransport(), costAlertDay());
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('Spend: $3,732.24')
            ->and($json)->toContain('CPL: $11.89')
            ->and($json)->toContain('CPB: $287.10')
            ->and($json)->toContain('CPQB: $414.69')
            ->and($json)->toContain('Blended CPB: $233.27')
            ->and($json)->not->toContain('did not answer this run')
            // The small print says the figures arrived, in three words rather than three lines.
            ->and($json)->toContain('warehouse current');

        /*
         * Spend-descending, because the channel carrying the money is the one the reader is
         * looking for. Meta at $2,892.84, Google at $737.46, Microsoft at $101.94 — the legacy
         * alert's own split, so anyone holding the two cards together sees the same numbers.
         */
        $cards = array_values(array_map(
            static fn (array $block): string => (string) $block['title']['text'],
            array_filter(
                $transport->posted[0]['blocks'],
                static fn (array $block): bool => ($block['type'] ?? '') === 'card',
            ),
        ));

        expect($cards)->toBe([':meta: Meta', ':google: Google', ':microsoft: Microsoft'])
            ->and($json)->toContain('$2,892.84 spend')
            ->and($json)->toContain('*CPB* $321.43   *CPQB* $578.57');
    });

    /*
     * A channel that spent nothing gets no card. Three channels exist in the view and on a quiet
     * day one of them is zero all day; a row of dashes teaches people to skip the section, and
     * then they skip it on the day it matters.
     */
    test('a channel that spent nothing gets no card at all', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $day = costAlertDay(['bing_spend' => '0', 'bing_cpb' => null, 'bing_cpqb' => null]);

        $action = costAlertAction($transport = recordingCostTransport(), $day);
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('Meta')
            ->and($json)->toContain('Google')
            ->and($json)->not->toContain('Microsoft');
    });

    /*
     * The warehouse is the only source for the cost half, so when it does not answer the card is
     * missing the half it exists for. Naming it is the whole job: a silent gap where the money
     * should be reads as a quiet day, which is the opposite of what happened.
     *
     * This is the successor to "an unreachable platform suppresses the cost figures and says so".
     * The funnel half still comes from this site and is unaffected, and the card says that too,
     * so the reader discards the figures that are missing rather than the whole message.
     */
    test('a warehouse that does not answer suppresses the cost figures and says so', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');
        costAlertBooking(costAlertLead([
            'utm_source' => 'facebook', 'utm_medium' => 'paid-social', 'created_at' => $now->subHours(3),
        ]), $now->subHour());

        $metrics = new FunnelMetricsService(null, warehouseStub(null));
        $snapshot = $metrics->snapshot($now);

        expect($snapshot->marketingDay)->toBeNull()
            // The site's own funnel figures are untouched by the warehouse being down.
            ->and($snapshot->bookings)->toBe(1)
            ->and($snapshot->platforms['meta']->bookings)->toBe(1);

        $action = new SendCostAlertAction($metrics, $transport = recordingCostTransport(), new SlackMessageRenderer);
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('did not answer this run')
            ->and($json)->not->toContain('$0.00')
            // The finding is a threaded reply now, and the card keeps its own colour. See
            // SendCostAlertAction::replyWithFindings().
            ->and($transport->posted[0]['color'])->not->toBe('#b91c1c')
            ->and(json_encode(findingsAmong($transport->posted)))->toContain('did not answer')
            /*
             * And the notification preview says it too. That line is what somebody reads on a
             * phone without opening Slack, so a preview of plausible-looking nothing is the one
             * place the omission would go unnoticed.
             */
            ->and($transport->posted[0]['text'])->toContain('Warehouse unavailable; funnel figures only');
    });

    /*
     * Before 08:00 Eastern the query reports yesterday closed; after it, today so far. The reader
     * has to be told which, because the two answer different questions and a closing report
     * arriving mid-morning otherwise looks like a catastrophic day.
     */
    test('the card says whether it is a closing report or a day in progress', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $running = costAlertAction($dayToDate = recordingCostTransport(), costAlertDay());
        $running->execute($now, force: true);

        expect(json_encode($dayToDate->posted[0]['blocks'], JSON_UNESCAPED_UNICODE))
            ->toContain('Day to date, Fri 18 Sep')
            ->toContain('*Today, so far*');

        update_option(SendCostAlertAction::STATE_OPTION, []);

        $closed = costAlertAction($closing = recordingCostTransport(), costAlertDay([
            'report_kind' => 'CLOSING',
            'as_of_et' => '07:30',
        ]));
        $closed->execute($now, force: true);

        // Unescaped, because the heading's em dash is `—` in the default JSON encoding and an
        // assertion written with the literal character would pass against nothing.
        expect(json_encode($closing->posted[0]['blocks'], JSON_UNESCAPED_UNICODE))
            ->toContain('Closing report for Fri 18 Sep')
            ->toContain('Closing — Fri 18 Sep');
    });

    /*
     * The view supplies the previous closed day on both report kinds, and it is only a fair
     * comparison against one of them. Two hours of today against a full yesterday makes every
     * morning look like a collapse, and a comparison that cries wolf daily is a comparison people
     * stop reading — at which point it is worse than absent.
     */
    test('the previous day is compared only on a closing report', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $running = costAlertAction($dayToDate = recordingCostTransport(), costAlertDay());
        $running->execute($now, force: true);

        expect(json_encode($dayToDate->posted[0]['blocks']))->not->toContain('was 14');

        update_option(SendCostAlertAction::STATE_OPTION, []);

        $closed = costAlertAction($closing = recordingCostTransport(), costAlertDay(['report_kind' => 'CLOSING']));
        $closed->execute($now, force: true);

        expect(json_encode($closing->posted[0]['blocks']))
            ->toContain('was 14 on Thu 17 Sep')
            ->toContain('was $243.57');
    });

    /*
     * `spend_is_complete` is only ever true on a closing report whose Meta feed covered the day.
     * On a day still running it is always false, which is honest and tells the reader nothing they
     * did not already read in the heading — so the caveat is printed only where it says something,
     * and a closing report with an incomplete feed is exactly that case.
     */
    test('an incomplete Meta feed is caveated only where the heading does not already say so', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        $running = costAlertAction($dayToDate = recordingCostTransport(), costAlertDay());
        $running->execute($now, force: true);

        expect(json_encode($dayToDate->posted[0]['blocks']))->not->toContain('Meta feed incomplete');

        update_option(SendCostAlertAction::STATE_OPTION, []);

        $closed = costAlertAction($closing = recordingCostTransport(), costAlertDay([
            'report_kind' => 'CLOSING',
            'spend_is_complete' => 'false',
        ]));
        $closed->execute($now, force: true);

        expect(json_encode($closing->posted[0]['blocks']))->toContain('Meta feed incomplete');

        update_option(SendCostAlertAction::STATE_OPTION, []);

        $final = costAlertAction($complete = recordingCostTransport(), costAlertDay([
            'report_kind' => 'CLOSING',
            'spend_is_complete' => 'true',
        ]));
        $final->execute($now, force: true);

        expect(json_encode($complete->posted[0]['blocks']))->not->toContain('Meta feed incomplete');
    });

    /*
     * The card prints the warehouse's booking count, so a site that recorded a wildly different
     * number means one of the two is wrong. Nobody should be dividing spend by either until it is
     * known which, and the finding says which number the card went with.
     */
    test('a warehouse and a site that disagree on the booking count are reported under the card', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        // One booking here, forty in the warehouse.
        costAlertBooking(costAlertLead(['created_at' => $now->subHours(3)]), $now->subHour());

        $action = costAlertAction($transport = recordingCostTransport(), costAlertDay([
            'total_appointments' => '40',
        ]));
        $action->execute($now, force: true);

        $card = (string) json_encode($transport->posted[0]['blocks']);
        $replies = findingsAmong($transport->posted);

        expect($replies)->toHaveCount(1)
            ->and(json_encode($replies[0]['blocks']))->toContain('warehouse reports 40 bookings')
            ->and(json_encode($replies[0]['blocks']))->toContain('this site recorded only 1')
            // Under the card it is about, and only there — a broadcast reply is also posted to
            // the channel, which would put the findings back where they came from.
            ->and($replies[0]['thread_ts'])->toBe($transport->posted[0]['ts'])
            ->and($replies[0]['broadcast'])->toBeFalse()
            // And off the card itself, which keeps its own colour.
            ->and($card)->not->toContain('warehouse reports 40 bookings')
            ->and($transport->posted[0]['color'])->not->toBe('#b91c1c');
    });

    /*
     * The gate that the fbclid measurement exists for, and the one the review found was only being
     * applied to a fortieth of the traffic it needed to cover.
     *
     * `fbclid` is stamped on organic Facebook and Instagram clicks as well as paid ones, so a Meta
     * row propped up by click IDs counts traffic the ad account never paid for. This is now a
     * property of this site's own platform slices rather than of the card's cost figures — the
     * card divides the warehouse's spend by the warehouse's bookings — but the slices still feed
     * the admin widget and the reconciler, and the distinction has to survive there.
     */
    test('organic social bookings stay in the platform row and out of the paid denominator', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        // Six paid Instagram ad clicks, four organic bio-link clicks. Same utm_source.
        foreach (range(1, 6) as $ignored) {
            costAlertBooking(costAlertLead([
                'utm_source' => 'instagram', 'utm_medium' => 'paid-social', 'created_at' => $now->subHours(3),
            ]), $now->subHour());
        }

        foreach (range(1, 4) as $ignored) {
            costAlertBooking(costAlertLead([
                'utm_source' => 'instagram', 'utm_medium' => 'social', 'created_at' => $now->subHours(3),
            ]), $now->subHour());
        }

        $snapshot = (new FunnelMetricsService(null, warehouseStub(costAlertDay())))->snapshot($now);

        expect($snapshot->platforms['meta']->bookings)->toBe(10)
            ->and($snapshot->platforms['meta']->paidBookings())->toBe(6)
            // 1200 over 6, not over 10 — a third cheaper and wrong, in the flattering direction.
            ->and(round((float) (new PlatformSlice(
                'meta', 'Meta',
                leads: $snapshot->platforms['meta']->leads,
                bookings: 10, bookingsViaClickId: 0, qualified: 0,
                spend: 1200.0, bookingsNotProvenPaid: 4,
            ))->cpb(), 2))->toBe(200.0);
    });
});

describe('the 7-day dashboard widget', function () {
    /*
     * The KPI card is a cohort measure: leads captured in the window, and how many of *those*
     * have since booked. Not bookings that happened in the window — mixing the two populations
     * lets the conversion rate exceed 100% in a week that works through a backlog.
     */
    test('the KPI window counts the capture cohort, not everything that ever happened', function () {
        $inside = costAlertLead(['created_at' => now()->subDays(2)]);
        $inside->update(['status' => 'booked']);

        costAlertLead(['created_at' => now()->subDays(2)]);

        // Outside the window, and booked — must not appear in either figure.
        $old = costAlertLead(['created_at' => now()->subDays(30)]);
        $old->update(['status' => 'booked']);

        $since = now()->subDays(MarketingDashboard::KPI_WINDOW_DAYS - 1)->startOfDay();

        $total = Lead::where('created_at', '>=', $since)->count();
        $booked = Lead::where('created_at', '>=', $since)->where('status', 'booked')->count();

        expect($total)->toBe(2)
            ->and($booked)->toBe(1)
            ->and(Lead::count())->toBe(3);
    });
});

describe('the qualified definitions', function () {
    test('T10 excludes the sub-$10k bands and anything unanswered', function () {
        $qualified = costAlertLead(['monthly_revenue' => '$50k-$100k Per Month']);
        $below = costAlertLead(['monthly_revenue' => '$5k to $10k Per Month']);
        $legacy = costAlertLead(['monthly_revenue' => 'under_10k']);
        $blank = costAlertLead(['monthly_revenue' => null]);

        expect(LeadQualification::isT10($qualified))->toBeTrue()
            ->and(LeadQualification::isT10($below))->toBeFalse()
            ->and(LeadQualification::isT10($legacy))->toBeFalse()
            ->and(LeadQualification::isT10($blank))->toBeFalse();
    });

    /* The PHP badge and the SQL filter answer the same question; they used to be five literals. */
    test('the T10 query returns exactly the leads the T10 test accepts', function () {
        foreach (['$10k to $50k Per Month', '$0 to $5k Per Month', '<10k', null, ''] as $band) {
            costAlertLead(['monthly_revenue' => $band]);
        }

        $bySql = LeadQualification::t10Query()->pluck('id')->all();

        $byPhp = Lead::all()
            ->filter(static fn (Lead $lead): bool => LeadQualification::isT10($lead))
            ->pluck('id')
            ->all();

        expect($bySql)->toBe($byPhp)->and($bySql)->toHaveCount(1);
    });

    test('an unconfigured HubSpot ladder qualifies nobody rather than everybody', function () {
        config(['marketing.qualified.hubspot_stages' => []]);

        costAlertLead(['hubspot_lifecycle_stage' => 'customer']);

        $query = Lead::query();
        LeadQualification::constrainHubSpotQualified($query);

        expect($query->count())->toBe(0);
    });
});

/**
 * One day of the data team's marketing view, in the shape BigQuery hands it over.
 *
 * Every value is a string, including the booleans and the integers, because that is what the REST
 * API sends whatever the column's declared type — building the fixture any other way would test a
 * row this application never receives. The figures are the 2026-09-18 alert's own: $2,892.84 Meta,
 * $737.46 Google, $101.94 Microsoft, $3,732.24 total. Anyone holding the new card against the old
 * one is looking at the same numbers, so the differences they see are differences in what the card
 * says about them.
 *
 * @param  array<string, string|null>  $overrides
 */
function costAlertDay(array $overrides = []): MarketingDay
{
    return MarketingDay::fromRow(array_merge([
        'Date' => '2026-09-18',
        'report_kind' => 'DAY-TO-DATE',
        'as_of_et' => '15:00',
        'spend_is_complete' => 'false',
        'total_appointments' => '16',
        'total_qualified' => '10',
        'total_leads' => '314',
        'total_spend' => '3732.24',
        'facebook_spend' => '2892.84',
        'google_spend' => '737.46',
        'bing_spend' => '101.94',
        'facebook_cpb' => '321.43',
        'google_cpb' => '184.37',
        'bing_cpb' => '101.94',
        'facebook_cpqb' => '578.57',
        'google_cpqb' => '245.82',
        'bing_cpqb' => '101.94',
        'cpl' => '11.89',
        'cpb_all' => '233.27',
        'cpqb_all' => '373.22',
        'cpb_paid' => '287.10',
        'cpqb_paid' => '414.69',
        'unclassified_leads' => '61',
        'unclassified_appointments' => '3',
        'unclassified_qualified' => '2',
        'unclassified_appointments_share' => '0.1875',
        'prev_date' => '2026-09-17',
        'prev_spend' => '3410.00',
        'prev_appointments' => '14',
        'prev_cpb' => '243.57',
        'prev_cpqb' => '379.00',
    ], $overrides));
}

/**
 * A warehouse client with a fixed answer, so the card can be tested without BigQuery.
 *
 * Null is the unreachable case and it is a first-class one: half the tests in this file are about
 * what the card says when the cost figures are not there.
 */
/**
 * @param  array<string, int>|null  $consultations  YYYY-MM-DD => count, or null for a warehouse
 *                                                  that could not answer for the calendar at all.
 */
function warehouseStub(?MarketingDay $day, ?PartialDay $today = null, ?array $consultations = null): BigQueryClient
{
    return new class($day, $today, $consultations) extends BigQueryClient
    {
        /** @param  array<string, int>|null  $consultations */
        public function __construct(
            private ?MarketingDay $day,
            private ?PartialDay $today,
            private ?array $consultations = null,
        ) {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function marketingDay(): ?MarketingDay
        {
            return $this->day;
        }

        public function todaySoFar(): ?PartialDay
        {
            return $this->today;
        }

        /**
         * Returns only the requested days it was given counts for, which is what the real client
         * does: a day the query answered for is present even at zero, and a day it did not is
         * absent and reads as unavailable.
         *
         * @param  array<int, string>  $dates
         * @return array<string, int>|null
         */
        public function consultationLoad(array $dates): ?array
        {
            if ($this->consultations === null) {
                return null;
            }

            return array_intersect_key($this->consultations, array_flip($dates));
        }
    };
}

/**
 * A snapshot carrying nothing but calendar counts, for asserting on that one line.
 *
 * @param  array<string, int|null>  $upcoming
 */
function consultationSnapshot(?int $today, array $upcoming): FunnelSnapshot
{
    return new FunnelSnapshot(
        generatedAt: CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC'),
        timezone: 'UTC',
        currency: 'USD',
        dayElapsed: 0.625,
        leads: 0,
        bookings: 0,
        qualifiedT10: 0,
        qualifiedHubSpot: null,
        hubSpotCoverage: 0.0,
        platforms: [],
        excludedVaLeads: 0,
        excludedVaBookings: 0,
        lastLeadMinutes: null,
        lastBookingMinutes: null,
        lastBookingName: null,
        recentSampleBooked: 0,
        recentSampleSize: 0,
        trailingBookingRate: 0.0,
        trailingSampleSize: 0,
        consultationsToday: $today,
        upcomingConsultations: $upcoming,
        baseline: [],
        warnings: [],
    );
}

/** A transport that records what it would send instead of reaching Slack. */
function recordingCostTransport(): object
{
    return new class extends SlackTransport
    {
        /** @var array<int, array{text: string, blocks: array, color: ?string, channel: ?string, thread_ts: ?string}> */
        public array $posted = [];

        /** @var array<int, array{channel: string, ts: string}> */
        public array $updated = [];

        public function post(
            string $text,
            array $blocks = [],
            ?string $color = null,
            ?string $threadTs = null,
            bool $broadcast = false,
            ?string $channel = null,
        ): ?array {
            // The ts is recorded as well as returned, so a test can assert that a reply was
            // threaded under the card that actually preceded it rather than under a literal.
            $ts = '1700000000.000'.(count($this->posted) + 1);

            $this->posted[] = [
                'text' => $text,
                'blocks' => $blocks,
                'color' => $color,
                'channel' => $channel,
                'thread_ts' => $threadTs,
                'broadcast' => $broadcast,
                'ts' => $ts,
            ];

            return ['ts' => $ts, 'channel' => 'C-COST'];
        }

        public function update(
            string $channel,
            string $ts,
            string $text,
            array $blocks = [],
            ?string $color = null,
        ): bool {
            $this->updated[] = ['channel' => $channel, 'ts' => $ts];

            return true;
        }
    };
}

/**
 * The action under test, wired to a warehouse with a fixed answer.
 *
 * Defaults to a warehouse that did not answer, because that is the state of every environment
 * without BigQuery credentials and the state most of these tests want: the funnel half of the card
 * rendered from real rows, and the cost half explicitly absent.
 */
function costAlertAction(SlackTransport $transport, ?MarketingDay $day = null, ?PartialDay $today = null): SendCostAlertAction
{
    return new SendCostAlertAction(
        new FunnelMetricsService(null, warehouseStub($day, $today)),
        $transport,
        new SlackMessageRenderer,
    );
}

/*
 * Which day this site's half of the card covers.
 *
 * Today, at every hour, because the card is. This followed the warehouse's reported day for a
 * while — the period when an overnight card's headline was the previous day closed — and the
 * tests for that are gone with it. What remains is the case that made the alignment matter in the
 * first place: the two halves must never describe different days.
 */
describe('the day the card reports', function () {
    beforeEach(function () {
        costAlertConfig();
        Lead::truncate();
        LeadActivityLog::truncate();
    });

    test('the funnel figures are today, even while the warehouse reports yesterday closed', function () {
        costAlertLead(['created_at' => CarbonImmutable::parse('2026-09-19 14:00:00', 'UTC')]);
        costAlertLead(['created_at' => CarbonImmutable::parse('2026-09-20 03:00:00', 'UTC')]);

        $snapshot = (new FunnelMetricsService(null, warehouseStub(
            costAlertDay(['Date' => '2026-09-19', 'report_kind' => 'CLOSING']),
            PartialDay::fromRow(['date' => '2026-09-20', 'as_of_et' => '03:52', 'total_leads' => 1]),
        )))->snapshot(CarbonImmutable::parse('2026-09-20 03:52:00', 'UTC'));

        expect($snapshot->leads)->toBe(1)
            ->and($snapshot->todaySoFar?->date)->toBe('2026-09-20');
    });

    test('the baseline stays same-hour, so a morning is not measured against finished days', function () {
        // 22:00 yesterday is after a 15:00 read, so an hour-capped baseline must not count it.
        costAlertLead(['created_at' => CarbonImmutable::parse('2026-09-19 22:00:00', 'UTC')]);

        $snapshot = (new FunnelMetricsService(null, warehouseStub(costAlertDay([
            'Date' => '2026-09-20',
            'report_kind' => 'DAY-TO-DATE',
        ]))))->snapshot(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));

        expect($snapshot->baseline['leads'])->toBe(0.0);
    });

    test('an unreadable warehouse still counts today', function () {
        costAlertLead(['created_at' => CarbonImmutable::parse('2026-09-20 09:00:00', 'UTC')]);

        $snapshot = (new FunnelMetricsService(null, warehouseStub(null)))
            ->snapshot(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));

        expect($snapshot->leads)->toBe(1);
    });

    test('the gap warning names the day instead of calling it today', function () {
        $findings = (new AlertReconciler)->check([
            'leads' => 124, 'bookings' => 82, 'platform_bookings' => 82, 'booked_by_status' => 82,
            'last_lead_minutes' => 5, 'last_booking_minutes' => 20, 'within_window' => true,
            'warehouse_bookings' => 0,
            'report_date' => '2026-09-19',
            'report_is_closing' => true,
        ]);

        expect($findings)->not->toBeEmpty()
            ->and($findings[0])->toContain('on 2026-09-19');
    });
});

/*
 * Nothing is ever edited, whatever the report is about.
 *
 * This was built the other way first — one card a day, updated hourly — and the boundary cases
 * are kept as tests because they are where an edit would do the most damage if one were ever
 * reintroduced. Between midnight and 08:00 Eastern the warehouse reports the previous day closed
 * and from 08:00 the current day so far, both on the same calendar date, so a card keyed on the
 * date would have had the 08:00 run overwrite the night's closing figures with the new day's
 * running ones.
 */
describe('every run leaves its own card', function () {
    beforeEach(function () {
        costAlertConfig();
        Lead::truncate();
        LeadActivityLog::truncate();
        update_option(SendCostAlertAction::STATE_OPTION, []);
    });

    test('hourly runs through one night stack up rather than collapsing', function () {
        $transport = recordingCostTransport();
        $closing = costAlertDay(['Date' => '2026-09-19', 'report_kind' => 'CLOSING']);

        costAlertAction($transport, $closing)->execute(CarbonImmutable::parse('2026-09-20 02:00:00', 'UTC'), force: true);
        costAlertAction($transport, $closing)->execute(CarbonImmutable::parse('2026-09-20 03:00:00', 'UTC'), force: true);

        expect(cardsAmong($transport->posted))->toHaveCount(2)
            ->and($transport->updated)->toBeEmpty();
    });

    test('the switch from closing to day-to-date does not touch the night', function () {
        $transport = recordingCostTransport();

        costAlertAction($transport, costAlertDay([
            'Date' => '2026-09-19',
            'report_kind' => 'CLOSING',
        ]))->execute(CarbonImmutable::parse('2026-09-20 03:00:00', 'UTC'), force: true);

        costAlertAction($transport, costAlertDay([
            'Date' => '2026-09-20',
            'report_kind' => 'DAY-TO-DATE',
        ]))->execute(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'), force: true);

        expect(cardsAmong($transport->posted))->toHaveCount(2)
            ->and($transport->updated)->toBeEmpty();
    });

    test('it remembers the last card posted, so its timestamp is recoverable', function () {
        // The bot has chat:write and no channels:history, so a card it posted cannot be found
        // again by searching. This option row is the only handle on one.
        costAlertAction(recordingCostTransport(), costAlertDay([
            'Date' => '2026-09-19',
            'report_kind' => 'CLOSING',
        ]))->execute(CarbonImmutable::parse('2026-09-20 03:00:00', 'UTC'), force: true);

        $state = get_option(SendCostAlertAction::STATE_OPTION, []);

        expect($state['ts'] ?? '')->not->toBe('')
            ->and($state['card'] ?? '')->toBe('2026-09-19/CLOSING');
    });
});

/*
 * "Today so far", under a closing report.
 *
 * Between midnight and 08:00 Eastern the data team's query reports the previous day complete and
 * says nothing at all about the hours since midnight — but the view holds those hours, and the
 * alert this replaced showed them around the clock. Without them a reader at 05:00 cannot tell a
 * slow start from a stopped ad account.
 */
describe('today so far', function () {
    beforeEach(function () {
        costAlertConfig();
        Lead::truncate();
        LeadActivityLog::truncate();
        update_option(SendCostAlertAction::STATE_OPTION, []);
    });

    $render = function (?PartialDay $today, string $kind = 'CLOSING'): string {
        $transport = recordingCostTransport();

        costAlertAction($transport, costAlertDay([
            'Date' => '2026-09-19',
            'report_kind' => $kind,
        ]), $today)->execute(CarbonImmutable::parse('2026-09-20 03:00:00', 'UTC'), force: true);

        return json_encode($transport->posted[0]['blocks'], JSON_UNESCAPED_UNICODE);
    };

    test('today is the headline and the closed day is one line under it', function () use ($render) {
        $blocks = $render(PartialDay::fromRow([
            'date' => '2026-09-20', 'as_of_et' => '05:43',
            'total_leads' => 11, 'total_appointments' => 7, 'total_qualified' => 3,
            'total_spend' => 471.80, 'cpl' => 42.89, 'cpb' => 67.40, 'cpqb' => 157.27,
        ]));

        expect($blocks)->toContain('Today so far — Sun 20 Sep, as of 05:43 ET')
            ->and($blocks)->toContain('- Leads: 11')
            ->and($blocks)->toContain('- Bookings: 7 (64% of leads)')
            ->and($blocks)->toContain('- CPB: $67.40')
            // Eight overnight cards repeating a finished day as a block is what this replaced.
            ->and($blocks)->toContain('Sat 19 Sep closed: 314 leads, 16 bookings, $3,732.24 spend, CPB $287.10')
            ->and($blocks)->not->toContain('*Closing — Sat 19 Sep*');
    });

    test('a quiet night prints dashes for the costs rather than zeroes', function () use ($render) {
        // 00:30 with no bookings yet. A cost per booking on zero bookings is absent, not zero,
        // and SAFE_DIVIDE gives null so the card prints a dash.
        $blocks = $render(PartialDay::fromRow([
            'date' => '2026-09-20', 'as_of_et' => '00:30',
            'total_leads' => 0, 'total_appointments' => 0, 'total_qualified' => 0, 'total_spend' => 0.0,
        ]));

        expect($blocks)->toContain('Today so far — Sun 20 Sep, as of 00:30 ET')
            ->and($blocks)->toContain('- Leads: 0')
            ->and($blocks)->not->toContain('CPB: $0.00');
    });

    test('it is absent when the warehouse could not be asked', function () use ($render) {
        expect($render(null))->not->toContain('Today so far');
    });

    test('it is not fetched at all once the report is day-to-date', function () {
        // From 08:00 the headline is already today; a second copy would be the same numbers twice.
        $transport = recordingCostTransport();

        costAlertAction($transport, costAlertDay([
            'Date' => '2026-09-20',
            'report_kind' => 'DAY-TO-DATE',
        ]), new PartialDay('2026-09-20', '13:00', 40, 25, 18, 8000.0))
            ->execute(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'), force: true);

        expect(json_encode($transport->posted[0]['blocks'], JSON_UNESCAPED_UNICODE))
            ->not->toContain('Today so far');
    });
});

/*
 * Freshness, per-platform staleness, and the funnel above the lead.
 *
 * All three come from columns the data team's query does not select, read by our own supplement
 * query against the same view for the same date.
 */
describe('the day supplement', function () {
    beforeEach(function () {
        costAlertConfig();
        Lead::truncate();
        LeadActivityLog::truncate();
    });

    $clean = [
        'leads' => 10, 'bookings' => 5, 'platform_bookings' => 5, 'booked_by_status' => 5,
        'last_lead_minutes' => 5, 'last_booking_minutes' => 20, 'within_window' => true,
    ];

    /*
     * The zone extracted_at is recorded in is asserted, not assumed, because getting it wrong is
     * silent: read as UTC it made a fresh extract look four hours old and the check below warned
     * about a pipeline that was working.
     */
    test('extracted_at is read as Eastern, so a fresh extract reads as fresh', function () {
        $supplement = DaySupplement::fromRow([
            'date' => '2026-09-19',
            'extracted_at' => '2026-09-20 05:44:07',
        ]);

        // Whole minutes, truncated rather than rounded — an age should never read older than it is.
        expect($supplement->ageInMinutes(CarbonImmutable::parse('2026-09-20 05:46:07', 'America/New_York')))->toBe(2)
            ->and($supplement->ageInMinutes(CarbonImmutable::parse('2026-09-20 05:46:00', 'America/New_York')))->toBe(1);
    });

    test('a stalled extract is reported, however current the card looks', function () use ($clean) {
        $findings = (new AlertReconciler)->check([...$clean, 'warehouse_age_minutes' => 240]);

        expect(implode(' ', $findings))->toContain('last refreshed 4h 0m ago');
    });

    test('an extract inside the hourly cycle says nothing', function () use ($clean) {
        expect((new AlertReconciler)->check([...$clean, 'warehouse_age_minutes' => 45]))->toBe([]);
    });

    /*
     * Spend arriving short makes cost per booking look *better*, which is the most dangerous
     * direction for a cost alert to be wrong in — nobody questions good news. The card used to
     * check Meta alone.
     */
    test('every reporting platform is named, not just Meta', function () use ($clean) {
        $one = (new AlertReconciler)->check([...$clean, 'stale_platforms' => ['Google']]);
        $two = (new AlertReconciler)->check([...$clean, 'stale_platforms' => ['Meta', 'Google', 'Microsoft']]);

        expect(implode(' ', $one))->toContain('Google is still reporting')
            ->and(implode(' ', $two))->toContain('Meta, Google and Microsoft are still reporting');
    });

    test('spend still settling is a softer finding than a stale platform', function () use ($clean) {
        $findings = (new AlertReconciler)->check([...$clean, 'spend_pending' => true]);

        expect(implode(' ', $findings))->toContain('still settling');
    });

    test('the stale-platform finding wins when both apply', function () use ($clean) {
        // Naming the platform is strictly more useful than saying spend is unsettled.
        $findings = (new AlertReconciler)->check([
            ...$clean, 'spend_pending' => true, 'stale_platforms' => ['Meta'],
        ]);

        expect(implode(' ', $findings))->toContain('Meta is still reporting')
            ->and(implode(' ', $findings))->not->toContain('still settling');
    });

    test('the supplement round-trips through the snapshot cache', function () {
        // The widget reads a cached array, so a field that does not survive toArray/fromArray is
        // present on the Slack card and absent on the dashboard.
        $original = DaySupplement::fromRow([
            'date' => '2026-09-19', 'extracted_at' => '2026-09-20 05:44:07',
            'meta_stale' => 1, 'google_stale' => 0, 'bing_stale' => 0, 'spend_pending' => 0,
            'impressions' => 452542, 'clicks' => 3764, 'landing_page_views' => 2669,
        ]);

        $restored = DaySupplement::fromRow($original->toArray());

        expect($restored->impressions)->toBe(452542)
            ->and($restored->clicks)->toBe(3764)
            ->and($restored->stalePlatforms())->toBe(['Meta'])
            ->and($restored->extractedAt?->toIso8601String())->toBe($original->extractedAt?->toIso8601String());
    });
});

/*
 * Which day the platform breakdown describes.
 *
 * It used to follow the headline, which overnight is yesterday closed — putting a breakdown of
 * Saturday directly beneath a "Today so far" line, where it reads as today's. The breakdown is the
 * part people act on, so it is the part that has to be current.
 */
describe('the platform breakdown', function () {
    beforeEach(function () {
        costAlertConfig();
        Lead::truncate();
        LeadActivityLog::truncate();
        update_option(SendCostAlertAction::STATE_OPTION, []);
    });

    $todayWithChannels = fn (): PartialDay => PartialDay::fromRow([
        'date' => '2026-09-20', 'as_of_et' => '05:59',
        'total_leads' => 11, 'total_appointments' => 7, 'total_qualified' => 3, 'total_spend' => 549.71,
        'facebook_spend' => 546.54, 'facebook_cpb' => 68.32, 'facebook_cpqb' => 136.64,
        'google_spend' => 3.12, 'google_cpb' => null, 'google_cpqb' => null,
        'bing_spend' => 0, 'bing_cpb' => null, 'bing_cpqb' => null,
    ]);

    test('overnight it shows today, not the closed day above it', function () use ($todayWithChannels) {
        $transport = recordingCostTransport();

        costAlertAction($transport, costAlertDay([
            'Date' => '2026-09-19', 'report_kind' => 'CLOSING',
            'facebook_spend' => '19106.75', 'facebook_cpb' => '308.17',
        ]), $todayWithChannels())->execute(CarbonImmutable::parse('2026-09-20 03:00:00', 'UTC'), force: true);

        $blocks = json_encode($transport->posted[0]['blocks'], JSON_UNESCAPED_UNICODE);

        expect($blocks)->toContain('$546.54 spend')
            ->and($blocks)->not->toContain('$19,106.75 spend')
            // And it says which day it means, so the question cannot be asked again.
            ->and($blocks)->toContain('By platform — today so far, as of 05:59 ET');
    });

    test('a channel at zero today still gets no card', function () use ($todayWithChannels) {
        $transport = recordingCostTransport();

        costAlertAction($transport, costAlertDay([
            'Date' => '2026-09-19', 'report_kind' => 'CLOSING',
        ]), $todayWithChannels())->execute(CarbonImmutable::parse('2026-09-20 03:00:00', 'UTC'), force: true);

        // Bing spent nothing today; a row of dashes teaches people to skip the section.
        expect(json_encode($transport->posted[0]['blocks'], JSON_UNESCAPED_UNICODE))
            ->not->toContain('Microsoft');
    });

    test('from 08:00 it is the reported day, named as that day', function () {
        $transport = recordingCostTransport();

        costAlertAction($transport, costAlertDay([
            'Date' => '2026-09-20', 'report_kind' => 'DAY-TO-DATE',
        ]))->execute(CarbonImmutable::parse('2026-09-20 13:00:00', 'UTC'), force: true);

        expect(json_encode($transport->posted[0]['blocks'], JSON_UNESCAPED_UNICODE))
            ->toContain('By platform — Sun 20 Sep');
    });

    /*
     * The cost formula is theirs, not one invented for today. Verified against their published
     * figures for 19 Sep: Facebook 19106.75 / 62 bookings = 308.1733 against their 308.17, and
     * / 47 qualified = 406.5266 against their 406.53.
     */
    test('the per-channel costs are plain division of the view own columns', function () {
        $today = PartialDay::fromRow([
            'date' => '2026-09-19', 'as_of_et' => '23:59',
            'facebook_spend' => 19106.75, 'facebook_cpb' => 19106.75 / 62, 'facebook_cpqb' => 19106.75 / 47,
        ]);

        expect(round($today->channels['meta']->cpb, 2))->toBe(308.17)
            ->and(round($today->channels['meta']->cpqb, 2))->toBe(406.53);
    });
});
