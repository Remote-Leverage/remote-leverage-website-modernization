<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadQualification;
use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Marketing\Actions\SendCostAlertAction;
use App\Domains\Marketing\Contracts\AdSpendSource;
use App\Domains\Marketing\Data\AdSpendReading;
use App\Domains\Marketing\Data\FunnelSnapshot;
use App\Domains\Marketing\Data\PlatformSlice;
use App\Domains\Marketing\Services\AdSpendCollector;
use App\Domains\Marketing\Services\AlertReconciler;
use App\Domains\Marketing\Services\FunnelMetricsService;
use App\Domains\Marketing\Support\AdPlatformCredentials;
use App\Domains\Marketing\Support\DemoSnapshot;
use App\Infrastructure\Slack\SlackTransport;
use App\Infrastructure\WordPress\Admin\MarketingDashboard;
use Carbon\CarbonImmutable;

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

function costAlertConfig(array $overrides = []): void
{
    $GLOBALS['_app_config'] = [];

    config(['marketing' => require __DIR__.'/../../config/marketing.php']);
    config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);

    // Fixtures are written in UTC, so the reporting day has to be UTC or "today" moves.
    config(['marketing.cost_alert.timezone' => 'UTC']);

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
});

describe('cost arithmetic', function () {
    /*
     * The legacy numbers, reproduced exactly, then corrected.
     *
     * $3,732.24 over 14 bookings is the $266.59 that alert printed. Over the 11 bookings a
     * platform could actually be named for, it is $339.29 — 27% higher, and the figure anyone
     * deciding where to put tomorrow's budget actually needs.
     */
    test('paid and blended cost per booking differ by the size of the attribution gap', function () {
        $snapshot = costSnapshot(spend: 3732.24, bookings: 14, unattributed: 3, qualified: 9, unattributedQualified: 2);

        expect(round((float) $snapshot->blendedCpb(), 2))->toBe(266.59)
            ->and(round((float) $snapshot->paidCpb(), 2))->toBe(339.29)
            ->and(round((float) $snapshot->blendedCpqb(), 2))->toBe(414.69)
            ->and(round((float) $snapshot->paidCpqb(), 2))->toBe(533.18)
            ->and($snapshot->attributedBookings())->toBe(11)
            ->and(round($snapshot->unattributedShare() * 100, 1))->toBe(21.4)
            ->and((int) round((float) $snapshot->blendedUnderstatement() * 100))->toBe(21);
    });

    test('with everything attributed the two figures are the same', function () {
        $snapshot = costSnapshot(spend: 1000.0, bookings: 10, unattributed: 0, qualified: 5, unattributedQualified: 0);

        expect($snapshot->paidCpb())->toBe($snapshot->blendedCpb())
            ->and($snapshot->blendedUnderstatement())->toBe(0.0);
    });

    test('no spend means no cost figures rather than zeroes', function () {
        $snapshot = costSnapshot(spend: null, bookings: 10, unattributed: 2, qualified: 5, unattributedQualified: 1);

        expect($snapshot->hasSpend())->toBeFalse()
            ->and($snapshot->paidCpb())->toBeNull()
            ->and($snapshot->blendedCpb())->toBeNull()
            ->and($snapshot->blendedUnderstatement())->toBeNull();
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

    test('the snapshot totals the paid denominator off the platform rows', function () {
        $snapshot = costSnapshot(
            spend: 3300.0, bookings: 14, unattributed: 3, qualified: 9,
            unattributedQualified: 2, notProvenPaid: 4,
        );

        expect($snapshot->attributedBookings())->toBe(11)
            ->and($snapshot->paidBookings())->toBe(7)
            ->and($snapshot->notProvenPaidBookings())->toBe(4)
            ->and(round((float) $snapshot->paidCpb(), 2))->toBe(471.43);
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

        $snapshot = (new FunnelMetricsService)->snapshot($now);

        $summed = array_sum(array_map(
            static fn (PlatformSlice $slice): int => $slice->bookings,
            $snapshot->platforms,
        ));

        expect($snapshot->bookings)->toBe(6)
            ->and($summed)->toBe(6)
            ->and($snapshot->platforms['meta']->bookings)->toBe(2)
            ->and($snapshot->platforms['google']->bookings)->toBe(2)
            ->and($snapshot->platforms['google']->bookingsViaClickId)->toBe(1)
            ->and($snapshot->unattributedBookings())->toBe(2)
            ->and($snapshot->attributedBookings())->toBe(4)
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
    test('says spend is not connected rather than printing zero', function () {
        $action = costAlertAction($transport = recordingCostTransport());

        $action->execute(CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC'), force: true);

        expect($transport->posted)->toHaveCount(1)
            ->and(json_encode($transport->posted[0]['blocks']))
            ->toContain('no ad platform is configured')
            ->not->toContain('$0.00');
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
        costAlertBooking(costAlertLead([
            'utm_source' => 'facebook', 'utm_medium' => 'paid-social', 'created_at' => $now->subHours(2),
        ]), $now->subHour());

        $action = costAlertAction($transport = recordingCostTransport());
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('Meta')
            ->and($json)->not->toContain(':meta:');
    });

    test('a configured logo is prefixed to the platform name', function () {
        config(['marketing.cost_alert.platform_emoji.meta' => ':meta:']);

        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');
        costAlertBooking(costAlertLead([
            'utm_source' => 'facebook', 'utm_medium' => 'paid-social', 'created_at' => $now->subHours(2),
        ]), $now->subHour());

        $action = costAlertAction($transport = recordingCostTransport());
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
            public function snapshot(?CarbonImmutable $now = null): FunnelSnapshot
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
     * House rule: no native emoji. A `:shortcode:` for a brand logo is a different thing and is
     * not what this guards — the pattern is unicode ranges, which a shortcode does not match.
     */
    test('carries no emoji', function () {
        $action = costAlertAction($transport = recordingCostTransport());
        $action->execute(CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC'), force: true);

        $json = json_encode($transport->posted[0]['blocks']);

        expect(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', (string) $json))->toBe(0);
    });

    test('is red only when the reconciliation found something', function () {
        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');

        $quiet = costAlertAction($clean = recordingCostTransport());
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

        $loud = costAlertAction($flagged = recordingCostTransport());
        $loud->execute($now, force: true);

        expect($flagged->posted[0]['color'])->toBe('#b91c1c');
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
            ->and($transport->posted)->toBeEmpty();

        // A person who typed the command has asked for it, wherever they are.
        expect($action->execute($now, force: true))->toBeTrue()
            ->and($transport->posted)->toHaveCount(1);

        // The forced run above remembered today's card, so without this the next one correctly
        // takes the edit path and posts nothing. That behaviour has its own test.
        update_option(SendCostAlertAction::STATE_OPTION, []);
        $GLOBALS['wp_environment_type'] = 'production';

        $second = costAlertAction($live = recordingCostTransport());

        expect($second->execute($now))->toBeTrue()
            ->and($live->posted)->toHaveCount(1);
    });

    test('an explicit enabled=false stops it even in production', function () {
        config(['marketing.cost_alert.environments' => ['production'], 'marketing.cost_alert.enabled' => false]);
        $GLOBALS['wp_environment_type'] = 'production';

        $action = costAlertAction($transport = recordingCostTransport());

        expect($action->execute(CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC')))->toBeFalse()
            ->and($transport->posted)->toBeEmpty();
    });

    test('stays quiet outside the reporting window unless forced', function () {
        $action = costAlertAction($transport = recordingCostTransport());
        $middleOfTheNight = CarbonImmutable::parse('2026-09-18 03:00:00', 'UTC');

        expect($action->execute($middleOfTheNight))->toBeFalse()
            ->and($transport->posted)->toBeEmpty()
            ->and($action->execute($middleOfTheNight, force: true))->toBeTrue();
    });

    test('posts the first card of the day and edits it afterwards', function () {
        $transport = recordingCostTransport();
        $action = costAlertAction($transport);

        $action->execute(CarbonImmutable::parse('2026-09-18 09:00:00', 'UTC'), force: true);
        $action->execute(CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC'), force: true);

        expect($transport->posted)->toHaveCount(1)
            ->and($transport->updated)->toHaveCount(1)
            ->and($transport->updated[0]['ts'])->toBe('1700000000.0001');
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

        expect($transport->posted)->toHaveCount(2)
            ->and($transport->updated)->toBeEmpty();
    });

    test('names both qualified definitions and why one is missing', function () {
        $now = CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC');
        costAlertBooking(costAlertLead(['created_at' => $now->subHours(2)]), $now->subHour());

        $action = costAlertAction($transport = recordingCostTransport());
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        /*
         * Both definitions still have to be named on the card, but as clauses rather than the
         * four paragraphs they used to be. What must survive the condensing is the *coverage
         * number* — a reader who cannot see how thin it is has no way to know the figure is
         * suppressed rather than zero.
         */
        expect($json)->toContain('$10k+ MRR')
            ->and($json)->toContain('HubSpot stage known for')
            ->and($json)->not->toContain('HubSpot-qualified 0');
    });
});

describe('spend, end to end', function () {
    /*
     * The whole point of the feature, exercised from a spend reading to the rendered card: the
     * paid denominator excludes the fbclid-only bookings, and the message shows paid and blended
     * side by side with the gap named.
     */
    test('spend reaches the card, and the two cost figures differ by the attribution gap', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        /*
         * Eight paid Meta bookings, two with no platform at all. `utm_medium` is what makes them
         * paid — it is the production convention (`paid-social` is 1,785 of the booked rows) and
         * without it LeadChannel has nothing to go on and keeps them out of the denominator,
         * which is the behaviour the next test covers.
         */
        foreach (range(1, 8) as $ignored) {
            costAlertBooking(costAlertLead([
                'utm_source' => 'facebook',
                'utm_medium' => 'paid-social',
                'created_at' => $now->subHours(3),
            ]), $now->subHour());
        }

        foreach (range(1, 2) as $ignored) {
            costAlertBooking(costAlertLead(['utm_source' => null, 'created_at' => $now->subHours(3)]), $now->subHour());
        }

        $metrics = new FunnelMetricsService(null, null, null, spendCollector(AdSpendReading::of('meta', 2000.0)));
        $snapshot = $metrics->snapshot($now);

        expect($snapshot->bookings)->toBe(10)
            ->and($snapshot->paidBookings())->toBe(8)
            ->and($snapshot->paidCpb())->toBe(250.0)     // 2000 / 8 attributed
            ->and($snapshot->blendedCpb())->toBe(200.0)  // 2000 / 10, the legacy figure
            ->and((int) round((float) $snapshot->blendedUnderstatement() * 100))->toBe(20)
            ->and($snapshot->platforms['meta']->spend)->toBe(2000.0);

        $action = new SendCostAlertAction($metrics, $transport = recordingCostTransport(), new SlackMessageRenderer);
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('$250.00')
            ->and($json)->toContain('$200.00')
            ->and($json)->toContain('20% cheaper')
            ->and($json)->not->toContain('no ad platform is configured');
    });

    /*
     * A configured platform that cannot be reached withholds every cost figure rather than
     * dividing by a total that is missing one. The card has to say why, or the gap where the
     * numbers were reads as a quiet day.
     */
    test('an unreachable platform suppresses the cost figures and says so', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');
        costAlertBooking(costAlertLead([
            'utm_source' => 'facebook', 'utm_medium' => 'paid-social', 'created_at' => $now->subHours(3),
        ]), $now->subHour());

        $metrics = new FunnelMetricsService(null, null, null, spendCollector(
            AdSpendReading::unreachable('meta', 'token expired'),
        ));

        $snapshot = $metrics->snapshot($now);

        expect($snapshot->hasSpend())->toBeFalse()
            ->and($snapshot->hasSpendIntegration())->toBeTrue()
            ->and($snapshot->platforms['meta']->spend)->toBeNull();

        $action = new SendCostAlertAction($metrics, $transport = recordingCostTransport(), new SlackMessageRenderer);
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('Cost figures suppressed')
            ->and($json)->toContain('token expired')
            ->and($transport->posted[0]['color'])->toBe('#b91c1c');
    });

    /*
     * A suspended account answers 200 with 0.00 spend. Left alone, that produced a card reading
     * `Spend $0.00`, `CPB $0.00` and — since zero is not above any target — "CPB is on target",
     * while the warning at the top said the account was disabled. The figures are what people
     * read, so the figures have to go.
     */
    test('a disabled ad account suppresses the cost figures rather than reporting zero', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');
        costAlertBooking(costAlertLead([
            'utm_source' => 'facebook', 'utm_medium' => 'paid-social', 'created_at' => $now->subHours(3),
        ]), $now->subHour());

        $metrics = new FunnelMetricsService(null, null, null, spendCollector(
            AdSpendReading::of('meta', 0.0, 'USD', 'UTC', 'account_status 2 (disabled)'),
        ));

        $snapshot = $metrics->snapshot($now);

        expect($snapshot->hasSpend())->toBeFalse()
            ->and($snapshot->paidCpb())->toBeNull()
            ->and($snapshot->hasAccountIssues())->toBeTrue()
            ->and(implode(' ', $snapshot->warnings))->toContain('disabled');

        $action = new SendCostAlertAction($metrics, $transport = recordingCostTransport(), new SlackMessageRenderer);
        $action->execute($now, force: true);

        $json = (string) json_encode($transport->posted[0]['blocks']);

        expect($json)->toContain('Cost figures suppressed')
            ->and($json)->not->toContain('on target');
    });

    /*
     * The gate that the fbclid measurement exists for, and the one the review found was only
     * being applied to a fortieth of the traffic it needed to cover.
     */
    test('organic social bookings stay in the platform row and out of the cost denominator', function () {
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

        $snapshot = (new FunnelMetricsService(null, null, null, spendCollector(
            AdSpendReading::of('meta', 1200.0),
        )))->snapshot($now);

        expect($snapshot->platforms['meta']->bookings)->toBe(10)
            ->and($snapshot->platforms['meta']->paidBookings())->toBe(6)
            ->and($snapshot->paidCpb())->toBe(200.0)   // 1200 / 6, not 1200 / 10
            ->and($snapshot->blendedCpb())->toBe(120.0);
    });

    /*
     * Qualified bookings are a subset of bookings, so cost per qualified booking can never be
     * below cost per booking. It was, because the two denominators were reduced differently.
     */
    test('cost per qualified booking is never cheaper than cost per booking', function () {
        $now = CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC');

        foreach ([['paid-social', '$10k to $50k Per Month'], ['social', '$10k to $50k Per Month'], ['social', '<10k']] as [$medium, $band]) {
            costAlertBooking(costAlertLead([
                'utm_source' => 'facebook', 'utm_medium' => $medium,
                'monthly_revenue' => $band, 'created_at' => $now->subHours(3),
            ]), $now->subHour());
        }

        $snapshot = (new FunnelMetricsService(null, null, null, spendCollector(
            AdSpendReading::of('meta', 900.0),
        )))->snapshot($now);

        expect($snapshot->paidBookings())->toBe(1)
            ->and($snapshot->paidQualified())->toBe(1)
            ->and($snapshot->paidCpb())->toBe(900.0)
            ->and($snapshot->paidCpqb())->toBe(900.0)
            ->and($snapshot->paidCpqb())->toBeGreaterThanOrEqual($snapshot->paidCpb());
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
 * A snapshot with only the fields the cost arithmetic reads.
 *
 * The attributed remainder is parked on a single `meta` slice. It has to live on a real platform
 * rather than only in the totals, because the paid denominator is summed from the platform rows —
 * that is what keeps the cost figures and the table the reader is looking at in agreement.
 */
function costSnapshot(
    ?float $spend,
    int $bookings,
    int $unattributed,
    int $qualified,
    int $unattributedQualified,
    int $notProvenPaid = 0,
): FunnelSnapshot {
    $blank = static fn (string $slug, int $booked, int $qual, int $unpaid = 0): PlatformSlice => new PlatformSlice(
        $slug, $slug, leads: 0, bookings: $booked, bookingsViaClickId: 0, qualified: $qual,
        spend: null, bookingsNotProvenPaid: $unpaid,
    );

    return new FunnelSnapshot(
        generatedAt: CarbonImmutable::parse('2026-09-18 15:00:00', 'UTC'),
        timezone: 'UTC',
        currency: 'USD',
        dayElapsed: 0.625,
        leads: 0,
        bookings: $bookings,
        qualifiedT10: $qualified,
        qualifiedHubSpot: null,
        hubSpotCoverage: 0.0,
        platforms: [
            'meta' => $blank('meta', $bookings - $unattributed, $qualified - $unattributedQualified, $notProvenPaid),
            'direct' => $blank('direct', $unattributed, $unattributedQualified),
            'other' => $blank('other', 0, 0),
        ],
        excludedVaLeads: 0,
        excludedVaBookings: 0,
        lastLeadMinutes: null,
        lastBookingMinutes: null,
        lastBookingName: null,
        recentSampleBooked: 0,
        recentSampleSize: 0,
        trailingBookingRate: 0.0,
        trailingSampleSize: 0,
        consultationsToday: null,
        upcomingConsultations: [],
        baseline: [],
        warnings: [],
        spend: $spend,
    );
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
        /** @var array<int, array{text: string, blocks: array, color: ?string, channel: ?string}> */
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
            $this->posted[] = ['text' => $text, 'blocks' => $blocks, 'color' => $color, 'channel' => $channel];

            return ['ts' => '1700000000.000'.count($this->posted), 'channel' => 'C-COST'];
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

/** A collector wired to one fixed Meta reading, so the snapshot can be tested without HTTP. */
function spendCollector(AdSpendReading $reading): AdSpendCollector
{
    return new AdSpendCollector([
        new class($reading) implements AdSpendSource
        {
            public function __construct(private AdSpendReading $reading) {}

            public function platform(): string
            {
                return 'meta';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function read(CarbonImmutable $day): AdSpendReading
            {
                return $this->reading;
            }
        },
    ]);
}

function costAlertAction(SlackTransport $transport): SendCostAlertAction
{
    return new SendCostAlertAction(new FunnelMetricsService, $transport, new SlackMessageRenderer);
}
