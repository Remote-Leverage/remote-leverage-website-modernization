<?php

declare(strict_types=1);

use App\Domains\Marketing\Contracts\AdSpendSource;
use App\Domains\Marketing\Data\AdSpendReading;
use App\Domains\Marketing\Gateways\MetaInsightsClient;
use App\Domains\Marketing\Services\AdSpendCollector;
use App\Domains\Marketing\Services\AlertReconciler;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/*
 * Reading spend back from an ad platform.
 *
 * Every test here is about one distinction: *unreachable is not zero*. A platform that cannot be
 * asked and a platform that spent nothing are opposite facts, and collapsing them produces a cost
 * per booking that is too low — the direction on which somebody raises a budget. The arithmetic
 * is the easy part; keeping those two apart through four layers is not.
 */

beforeEach(function () {
    // The Http facade caches its Factory for the life of the process, so without a hard swap
    // every test inherits the previous one's stubs. Same reason as MetaConversionsApiTest.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);

    $GLOBALS['_app_config'] = [];
    config(['marketing' => require __DIR__.'/../../config/marketing.php']);
    config([
        'marketing.cost_alert.timezone' => 'America/New_York',
        'marketing.ads.meta.access_token' => 'EAAT-test',
        'marketing.ads.meta.ad_account_id' => '1234567890',
        'marketing.ads.meta.api_version' => 'v21.0',
    ]);
});

/** Meta's two endpoints, steered per test. */
function fakeMeta(array $account = [], array $insights = [], int $accountStatus = 200, int $insightsStatus = 200): void
{
    Http::fake(function (Request $request) use ($account, $insights, $accountStatus, $insightsStatus) {
        if (str_contains($request->url(), '/insights')) {
            return Http::response($insights, $insightsStatus);
        }

        return Http::response($account + [
            'currency' => 'USD',
            'timezone_name' => 'America/New_York',
            'account_status' => 1,
        ], $accountStatus);
    });
}

/** A source with a fixed answer, for testing the collector without HTTP. */
function stubSource(string $platform, ?AdSpendReading $reading, bool $configured = true): AdSpendSource
{
    return new class($platform, $reading, $configured) implements AdSpendSource
    {
        public function __construct(
            private string $slug,
            private ?AdSpendReading $reading,
            private bool $configured,
        ) {}

        public function platform(): string
        {
            return $this->slug;
        }

        public function isConfigured(): bool
        {
            return $this->configured;
        }

        public function read(CarbonImmutable $day): AdSpendReading
        {
            return $this->reading ?? AdSpendReading::unreachable($this->slug, 'no stub');
        }
    };
}

describe('the Meta insights client', function () {
    $day = CarbonImmutable::parse('2026-09-18 15:00:00', 'America/New_York');

    test('reads the day and reports spend, currency and timezone', function () use ($day) {
        fakeMeta(insights: ['data' => [['spend' => '2892.84']]]);

        $reading = (new MetaInsightsClient)->read($day);

        expect($reading->reachable)->toBeTrue()
            ->and($reading->spend)->toBe(2892.84)
            ->and($reading->currency)->toBe('USD')
            ->and($reading->timezone)->toBe('America/New_York')
            ->and($reading->hasAccountIssue())->toBeFalse();
    });

    /*
     * Meta returns spend as a decimal string, and an account that ran nothing returns no rows at
     * all rather than a row of zero. That empty array is a real answer and must read as 0.00 —
     * it is the one case where zero is the truth.
     */
    test('an empty data array is zero spend, not an unreachable platform', function () use ($day) {
        fakeMeta(insights: ['data' => []]);

        $reading = (new MetaInsightsClient)->read($day);

        expect($reading->reachable)->toBeTrue()
            ->and($reading->spend)->toBe(0.0);
    });

    test('a response with no recognisable data is unreachable rather than zero', function () use ($day) {
        fakeMeta(insights: ['unexpected' => true]);

        $reading = (new MetaInsightsClient)->read($day);

        expect($reading->reachable)->toBeFalse()
            ->and($reading->spend)->toBeNull();
    });

    /*
     * Meta distinguishes an expired token, a token missing `ads_read` and an ad account the token
     * cannot see. Those need three different fixes, so the message is carried through verbatim
     * rather than collapsed into "request failed".
     */
    test("Meta's own error message survives to the reading", function () use ($day) {
        fakeMeta(insights: ['error' => ['message' => '(#200) Requires ads_read permission']], insightsStatus: 403);

        $reading = (new MetaInsightsClient)->read($day);

        expect($reading->reachable)->toBeFalse()
            ->and($reading->error)->toContain('ads_read');
    });

    /*
     * The failure this endpoint makes easy to miss: a disabled account answers 200 with spend
     * 0.00. Without the status field that is indistinguishable from a quiet day.
     */
    test('a disabled account is flagged even though the call succeeded', function () use ($day) {
        fakeMeta(
            account: ['account_status' => 2, 'disable_reason' => 1],
            insights: ['data' => [['spend' => '0']]],
        );

        $reading = (new MetaInsightsClient)->read($day);

        expect($reading->reachable)->toBeTrue()
            ->and($reading->spend)->toBe(0.0)
            ->and($reading->hasAccountIssue())->toBeTrue()
            ->and($reading->accountIssue)->toContain('disabled')
            ->and($reading->accountIssue)->toContain('disable_reason 1');
    });

    test('the account timezone is reported so a mismatch can be caught', function () use ($day) {
        fakeMeta(
            account: ['timezone_name' => 'America/Los_Angeles'],
            insights: ['data' => [['spend' => '10']]],
        );

        expect((new MetaInsightsClient)->read($day)->timezone)->toBe('America/Los_Angeles');
    });

    test('the request asks for one explicit day rather than a preset', function () use ($day) {
        fakeMeta(insights: ['data' => [['spend' => '1']]]);

        (new MetaInsightsClient)->read($day);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/insights')) {
                return false;
            }

            // A preset is resolved against the account timezone with no way to see what it chose.
            return str_contains(urldecode($request->url()), '"since":"2026-09-18"')
                && str_contains(urldecode($request->url()), '"until":"2026-09-18"')
                && ! str_contains($request->url(), 'date_preset');
        });
    });

    test('the ad account id is prefixed once, however it was entered', function () use ($day) {
        fakeMeta(insights: ['data' => [['spend' => '1']]]);

        config(['marketing.ads.meta.ad_account_id' => 'act_1234567890']);
        (new MetaInsightsClient)->read($day);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/act_1234567890/')
            && ! str_contains($r->url(), 'act_act_'));
    });

    test('an unconfigured client does not make a request at all', function () use ($day) {
        Http::fake();
        config(['marketing.ads.meta.access_token' => '', 'marketing.ads.meta.ad_account_id' => '']);

        $reading = (new MetaInsightsClient)->read($day);

        expect($reading->reachable)->toBeFalse();
        Http::assertNothingSent();
    });
});

describe('the spend collector', function () {
    $day = CarbonImmutable::parse('2026-09-18 15:00:00', 'America/New_York');

    test('totals what every configured source reported', function () use ($day) {
        $collector = new AdSpendCollector([
            stubSource('meta', AdSpendReading::of('meta', 2892.84)),
            stubSource('google', AdSpendReading::of('google', 737.46)),
        ]);

        $readings = $collector->collect($day);

        expect(round((float) $collector->total($readings), 2))->toBe(3630.30)
            ->and($collector->unreachable($readings))->toBe([]);
    });

    /*
     * The rule the whole feature turns on. Meta answers, Google does not, and the sum of what came
     * back is not today's spend — it is today's spend minus an unknown amount. Dividing bookings
     * by it reports a cost per booking that is too low, and too low is the reading somebody
     * increases a budget on.
     */
    test('one unreachable platform suppresses the total entirely', function () use ($day) {
        $collector = new AdSpendCollector([
            stubSource('meta', AdSpendReading::of('meta', 2892.84)),
            stubSource('google', AdSpendReading::unreachable('google', 'token expired')),
        ]);

        $readings = $collector->collect($day);

        expect($collector->total($readings))->toBeNull()
            ->and($collector->unreachable($readings))->toBe(['google' => 'token expired'])
            // The platform that did answer keeps its own figure; only the total is withheld.
            ->and($readings['meta']->spend)->toBe(2892.84);
    });

    test('a source that is merely not configured is skipped, not failed', function () use ($day) {
        $collector = new AdSpendCollector([
            stubSource('meta', AdSpendReading::of('meta', 100.0)),
            stubSource('google', null, configured: false),
        ]);

        $readings = $collector->collect($day);

        expect($readings)->toHaveCount(1)
            ->and($collector->total($readings))->toBe(100.0);
    });

    test('nothing configured is no total rather than a total of zero', function () use ($day) {
        $collector = new AdSpendCollector([stubSource('meta', null, configured: false)]);

        expect($collector->total($collector->collect($day)))->toBeNull();
    });

    test('account issues and timezone mismatches are surfaced separately', function () use ($day) {
        $collector = new AdSpendCollector([
            stubSource('meta', AdSpendReading::of(
                'meta', 0.0, 'USD', 'America/Los_Angeles', 'account_status 2 (disabled)',
            )),
        ]);

        $readings = $collector->collect($day);

        expect($collector->accountIssues($readings))->toBe(['meta' => 'account_status 2 (disabled)'])
            ->and($collector->timezoneMismatches($readings, 'America/New_York'))
            ->toBe(['meta' => 'America/Los_Angeles'])
            /*
             * The account answered, and what it answered was 0.00 because it is suspended. That
             * is true and useless: totalling it produces a cost per booking of zero that reads as
             * excellent performance. An unhealthy account withholds the total exactly as an
             * unreachable one does.
             */
            ->and($collector->total($readings))->toBeNull();
    });

    test('a matching timezone is not reported as a mismatch', function () use ($day) {
        $collector = new AdSpendCollector([
            stubSource('meta', AdSpendReading::of('meta', 5.0, 'USD', 'America/New_York')),
        ]);

        expect($collector->timezoneMismatches($collector->collect($day), 'America/New_York'))->toBe([]);
    });
});

describe('what the alert says about a broken platform', function () {
    $clean = [
        'leads' => 40,
        'bookings' => 10,
        'last_lead_minutes' => 12,
        'last_booking_minutes' => 30,
        'platform_bookings' => 10,
        'within_window' => true,
    ];

    test('an unreachable platform explains the missing cost figures', function () use ($clean) {
        $findings = (new AlertReconciler)->check([
            ...$clean,
            'spend_unreachable' => ['google' => 'token expired'],
        ]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('Google')
            ->and($findings[0])->toContain('token expired')
            ->and($findings[0])->toContain('too cheap');
    });

    test('a disabled account is reported as a stop, not as a quiet day', function () use ($clean) {
        $findings = (new AlertReconciler)->check([
            ...$clean,
            'account_issues' => ['meta' => 'account_status 2 (disabled)'],
        ]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('disabled')
            ->and($findings[0])->toContain('not that the campaigns are quiet');
    });

    test('a timezone mismatch is reported rather than silently corrected', function () use ($clean) {
        config(['marketing.cost_alert.timezone' => 'America/New_York']);

        $findings = (new AlertReconciler)->check([
            ...$clean,
            'timezone_mismatches' => ['meta' => 'America/Los_Angeles'],
        ]);

        expect($findings)->toHaveCount(1)
            ->and($findings[0])->toContain('America/Los_Angeles')
            ->and($findings[0])->toContain('different windows');
    });
});
