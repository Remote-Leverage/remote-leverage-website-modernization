<?php

declare(strict_types=1);

use App\Domains\Lead\Services\LeadSettingsService;
use App\Domains\Marketing\Data\MarketingDay;
use App\Domains\Marketing\Gateways\BigQueryClient;
use App\Domains\Marketing\Support\WarehouseOAuth;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/*
 * Reading the marketing day out of the data team's warehouse.
 *
 * This file used to test three ad platform clients — Meta REST, Google GAQL and a hand-built
 * Microsoft SOAP report flow — and the collector that summed them. All four are deleted. Spend,
 * channel attribution and the booking counts that divide into them now arrive as one BigQuery row,
 * and every test here is about getting that row into typed fields without losing a distinction on
 * the way.
 *
 * The distinction that matters most is unchanged from the file this replaces: *nothing is not
 * zero*. It used to be "a platform that cannot be asked is not a platform that spent nothing".
 * Now it is `SAFE_DIVIDE`, which returns null when the denominator is zero — a cost per booking of
 * null means nothing booked, where 0.00 would mean every booking was free. Collapsing the two
 * produces a cost figure that is too low, which is the direction somebody raises a budget on.
 *
 * resources/sql/marketing-home-daily.sql names this file's job explicitly: the query is owned by
 * the data team and replaced wholesale when they change it, so the column names the mapper depends
 * on have to fail loudly here rather than render a card of dashes.
 */

beforeEach(function () {
    // The Http facade caches its Factory for the life of the process, so without a hard swap
    // every test inherits the previous one's stubs. Same reason as MetaConversionsApiTest.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);

    $GLOBALS['_app_config'] = [];
    config(['marketing' => require __DIR__.'/../../config/marketing.php']);
    Cache::forget('rl_bigquery_access_token');

    config([
        'marketing.cost_alert.timezone' => 'America/New_York',
        'marketing.warehouse.credentials' => '',
        'marketing.warehouse.project_id' => '',
    ]);
});

/*
 * The client reads the query off disk through WordPress, which is not loaded here.
 *
 * Guarded and pointed at the real theme root, matching VideoSourceResolutionTest's stub exactly —
 * whichever file Pest loads first defines it and both behave the same. It has to resolve to the
 * actual theme, because the file it is asked for is the data team's real SQL: a stub returning a
 * temp path would make every warehouse test pass against a query that does not exist.
 */
$GLOBALS['rl_theme_dir'] ??= dirname(__DIR__, 2);

/**
 * One row of the view, in the shape BigQuery actually hands back.
 *
 * Every value is a string, including the booleans and the integers, because that is what the REST
 * API sends whatever the column's declared type. The column names and the arithmetic are lifted
 * from resources/sql/marketing-home-daily.sql; the figures are the 2026-09-18 alert's.
 *
 * @param  array<string, string|null>  $overrides
 * @return array<string, string|null>
 */
function warehouseRow(array $overrides = []): array
{
    return array_merge([
        'Date' => '2026-09-18',
        'report_kind' => 'DAY-TO-DATE',
        'as_of_et' => '15:00',
        'spend_is_complete' => 'false',
        'total_new_appts' => '16',
        'total_new_qualified' => '10',
        'total_appointments' => '16',
        'total_qualified' => '10',
        'total_leads' => '314',
        'total_spend' => '3732.24',
        'google_spend' => '737.46',
        'facebook_spend' => '2892.84',
        'bing_spend' => '101.94',
        'cpl' => '11.89',
        'cpb' => '233.27',
        'cpqb' => '373.22',
        'cpb_all' => '233.27',
        'cpqb_all' => '373.22',
        'cpb_paid' => '287.1',
        'cpqb_paid' => '414.69',
        'google_cpb' => '184.37',
        'facebook_cpb' => '321.43',
        'bing_cpb' => '101.94',
        'google_cpqb' => '245.82',
        'facebook_cpqb' => '578.57',
        'bing_cpqb' => '101.94',
        'unclassified_leads' => '61',
        'unclassified_appointments' => '3',
        'unclassified_qualified' => '2',
        'unclassified_appointments_share' => '0.1875',
        'prev_date' => '2026-09-17',
        'prev_spend' => '3410.0',
        'prev_appointments' => '14',
        'prev_cpb' => '243.57',
        'prev_cpqb' => '379.0',
    ], $overrides);
}

/**
 * The REST envelope: a schema of names, and a row of anonymous positional values.
 *
 * Reproduced rather than simplified, because the separation *is* the thing the client has to get
 * right — the values carry no names of their own.
 *
 * @param  array<string, string|null>  $row
 * @return array<string, mixed>
 */
function bigQueryResponse(array $row, bool $jobComplete = true): array
{
    return [
        'jobComplete' => $jobComplete,
        'schema' => [
            'fields' => array_map(
                static fn (string $name): array => ['name' => $name, 'type' => 'STRING'],
                array_keys($row),
            ),
        ],
        'rows' => [
            ['f' => array_map(static fn ($value): array => ['v' => $value], array_values($row))],
        ],
    ];
}

/**
 * A configured client whose token is already banked.
 *
 * The cached token is the ordinary state on every run but the first, and taking that path keeps
 * these tests about reading the row rather than about `openssl_sign` — which has its own failure
 * mode, its own error message, and nothing to do with whether a column is mapped correctly.
 */
function configureWarehouse(): void
{
    config([
        'marketing.warehouse.credentials' => json_encode([
            'client_email' => 'marketing-alert@rl-data-platform-dev.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nstub\n-----END PRIVATE KEY-----\n",
            'project_id' => 'rl-data-platform-dev',
        ]),
        'marketing.warehouse.project_id' => '',
    ]);

    Cache::put('rl_bigquery_access_token', 'ya29-cached', 3000);
}

/** @param array<string, mixed> $body */
function fakeBigQuery(array $body, int $status = 200): void
{
    Http::fake(['bigquery.googleapis.com/*' => Http::response($body, $status)]);
}

describe('the warehouse row', function () {
    test('every column the card prints lands on the field that prints it', function () {
        $day = MarketingDay::fromRow(warehouseRow());

        expect($day->date)->toBe('2026-09-18')
            ->and($day->reportKind)->toBe('DAY-TO-DATE')
            ->and($day->asOfEt)->toBe('15:00')
            ->and($day->appointments)->toBe(16)
            ->and($day->qualified)->toBe(10)
            ->and($day->leads)->toBe(314)
            ->and($day->spend)->toBe(3732.24)
            ->and($day->cpl)->toBe(11.89)
            ->and($day->cpbPaid)->toBe(287.1)
            ->and($day->cpqbPaid)->toBe(414.69)
            ->and($day->unclassifiedLeads)->toBe(61)
            ->and($day->unclassifiedAppointments)->toBe(3)
            ->and($day->unclassifiedQualified)->toBe(2)
            ->and($day->unclassifiedShare)->toBe(0.1875)
            ->and($day->previousDate)->toBe('2026-09-17')
            ->and($day->previousSpend)->toBe(3410.0)
            ->and($day->previousAppointments)->toBe(14)
            ->and($day->previousCpb)->toBe(243.57);
    });

    /*
     * `cpb_all` and `cpb_paid` are computed independently by the view — total spend over every
     * booking, against paid spend over paid bookings. They are the blended and paid figures this
     * alert has always kept apart, and the gap between them is the attribution debt: on the
     * 2026-09-18 numbers, $233.27 blended against $287.10 paid. Mapping either onto the other
     * would print one number twice and lose the whole point of separating them.
     */
    test('blended and paid cost per booking stay two different fields', function () {
        $day = MarketingDay::fromRow(warehouseRow());

        expect($day->cpbAll)->toBe(233.27)
            ->and($day->cpbPaid)->toBe(287.1)
            ->and($day->cpqbAll)->toBe(373.22)
            ->and($day->cpqbPaid)->toBe(414.69)
            ->and($day->cpbAll)->not->toBe($day->cpbPaid);
    });

    /*
     * The view says Facebook and Bing; the rest of this codebase says Meta and Microsoft, because
     * that is `LeadPlatform`'s vocabulary and it is what the leads screen, the CSV export and the
     * platform emoji config are all keyed on. The rename happens once, here. Get it wrong and the
     * channel cards silently lose their logos and their labels.
     */
    test('the view channel names are mapped onto this domain slugs', function () {
        $channels = MarketingDay::fromRow(warehouseRow())->channels;

        expect(array_keys($channels))->toBe(['meta', 'google', 'microsoft'])
            ->and($channels['meta']->spend)->toBe(2892.84)
            ->and($channels['meta']->cpb)->toBe(321.43)
            ->and($channels['meta']->cpqb)->toBe(578.57)
            ->and($channels['google']->spend)->toBe(737.46)
            ->and($channels['microsoft']->spend)->toBe(101.94)
            ->and($channels['microsoft']->cpb)->toBe(101.94);
    });

    /*
     * The single most important invariant in this file.
     *
     * `SAFE_DIVIDE` returns null, not zero, when nothing booked. A cost per booking of null means
     * "no bookings to divide by"; 0.00 means "every booking was free", and the card renders it as
     * a real figure beside a target it is comfortably under. A quiet morning would report the best
     * cost per booking the business has ever seen.
     */
    test('a null cost stays null and never becomes zero', function () {
        $day = MarketingDay::fromRow(warehouseRow([
            'total_appointments' => '0',
            'total_qualified' => '0',
            'cpb_all' => null,
            'cpqb_all' => null,
            'cpb_paid' => null,
            'cpqb_paid' => null,
            'facebook_cpb' => null,
            'unclassified_appointments_share' => null,
            'prev_cpb' => null,
        ]));

        expect($day->cpbAll)->toBeNull()
            ->and($day->cpqbAll)->toBeNull()
            ->and($day->cpbPaid)->toBeNull()
            ->and($day->cpqbPaid)->toBeNull()
            ->and($day->channels['meta']->cpb)->toBeNull()
            ->and($day->unclassifiedShare)->toBeNull()
            ->and($day->previousCpb)->toBeNull()
            // Spend is still a real number on that day; it is only the quotients that vanish.
            ->and($day->spend)->toBe(3732.24);
    });

    /*
     * An empty string is what a blank cell arrives as, and `(float) ''` is 0.0 — the same wrong
     * answer as above by a different route.
     */
    test('an empty cell is absent rather than zero', function () {
        $day = MarketingDay::fromRow(warehouseRow(['cpb_paid' => '', 'total_spend' => '']));

        expect($day->cpbPaid)->toBeNull()->and($day->spend)->toBeNull();
    });

    /*
     * A missing spend figure is not a day with no spend. The card prints "not reported for this
     * day" rather than $0.00 for exactly this reason.
     */
    test('a day with no spend reported is distinguishable from a day that spent nothing', function () {
        expect(MarketingDay::fromRow(warehouseRow(['total_spend' => null]))->spend)->toBeNull()
            ->and(MarketingDay::fromRow(warehouseRow(['total_spend' => '0']))->spend)->toBe(0.0);
    });

    /*
     * BigQuery sends booleans over REST as the strings "true" and "false". PHP casts both to true,
     * so `(bool) $row['spend_is_complete']` marks every day-to-date figure final — the caveat that
     * says the Meta feed has not closed yet would never print.
     */
    test('the string "false" is false', function () {
        expect(MarketingDay::fromRow(warehouseRow(['spend_is_complete' => 'false']))->spendIsComplete)->toBeFalse()
            ->and(MarketingDay::fromRow(warehouseRow(['spend_is_complete' => 'true']))->spendIsComplete)->toBeTrue()
            ->and(MarketingDay::fromRow(warehouseRow(['spend_is_complete' => null]))->spendIsComplete)->toBeFalse();
    });

    /* Before 08:00 Eastern the query reports yesterday closed; after it, today so far. */
    test('the report kind decides whether the day is closed', function () {
        expect(MarketingDay::fromRow(warehouseRow(['report_kind' => 'CLOSING']))->isClosing())->toBeTrue()
            ->and(MarketingDay::fromRow(warehouseRow())->isClosing())->toBeFalse();
    });

    /*
     * The previous day is supplied on both report kinds and is only fair on one. Comparing two
     * hours of today against a full previous day makes every morning look like a collapse, which
     * is how a comparison stops being read at all.
     */
    test('the previous day is only a fair comparison against a closed one', function () {
        expect(MarketingDay::fromRow(warehouseRow())->hasFairComparison())->toBeFalse()
            ->and(MarketingDay::fromRow(warehouseRow(['report_kind' => 'CLOSING']))->hasFairComparison())->toBeTrue()
            ->and(MarketingDay::fromRow(warehouseRow([
                'report_kind' => 'CLOSING',
                'prev_appointments' => null,
            ]))->hasFairComparison())->toBeFalse();
    });

    /*
     * The snapshot is cached as scalars rather than serialised objects — see
     * FunnelSnapshot::toArray() — so the warehouse row has to survive a trip out and back. It
     * round-trips through the same key names it was read with, one mapping rather than two, and
     * this is what stops the two drifting: a field added to `fromRow` and forgotten in `toRow`
     * reads correctly on the hourly run and comes back null on every dashboard load.
     */
    test('a cached row rebuilds into the same day', function () {
        $day = MarketingDay::fromRow(warehouseRow());
        $rebuilt = MarketingDay::fromRow($day->toRow());

        expect($rebuilt->toRow())->toBe($day->toRow())
            ->and($rebuilt->spend)->toBe($day->spend)
            ->and($rebuilt->cpbPaid)->toBe($day->cpbPaid)
            ->and($rebuilt->spendIsComplete)->toBe($day->spendIsComplete)
            ->and($rebuilt->channels['meta']->spend)->toBe($day->channels['meta']->spend)
            ->and($rebuilt->previousAppointments)->toBe($day->previousAppointments);
    });

    /* Null has to survive the round trip too, for the same reason it has to survive the read. */
    test('nulls survive the round trip as nulls', function () {
        $day = MarketingDay::fromRow(warehouseRow([
            'cpb_paid' => null,
            'total_spend' => null,
            'prev_date' => null,
            'prev_appointments' => null,
        ]));

        $rebuilt = MarketingDay::fromRow($day->toRow());

        expect($rebuilt->cpbPaid)->toBeNull()
            ->and($rebuilt->spend)->toBeNull()
            ->and($rebuilt->previousDate)->toBeNull()
            ->and($rebuilt->previousAppointments)->toBeNull()
            ->and($rebuilt->hasFairComparison())->toBeFalse();
    });

    /* A channel that spent nothing gets no card; three channels exist and one is often quiet. */
    test('a channel at zero spend is not active', function () {
        $channels = MarketingDay::fromRow(warehouseRow(['bing_spend' => '0', 'google_spend' => null]))->channels;

        expect($channels['meta']->isActive())->toBeTrue()
            ->and($channels['microsoft']->isActive())->toBeFalse()
            ->and($channels['google']->isActive())->toBeFalse();
    });
});

describe('signing in with Google', function () {
    beforeEach(function () {
        update_option(LeadSettingsService::OPTION_KEY, []);
        config([
            'marketing.warehouse.client_id' => '',
            'marketing.warehouse.client_secret' => '',
            'marketing.warehouse.refresh_token' => '',
        ]);
    });

    test('no sign-in button until an OAuth client exists', function () {
        expect(WarehouseOAuth::isReady())->toBeFalse();

        config(['marketing.warehouse.client_id' => 'cid', 'marketing.warehouse.client_secret' => 'secret']);

        expect(WarehouseOAuth::isReady())->toBeTrue();
    });

    /*
     * Google issues a refresh token only on the FIRST consent for a client and account. Without
     * `access_type=offline` there is none at all, and without `prompt=consent` an admin who has
     * authorised before gets an access token only — and the connection appears to work until the
     * hour is up.
     */
    test('the consent URL asks for a refresh token every time', function () {
        config(['marketing.warehouse.client_id' => 'cid', 'marketing.warehouse.client_secret' => 'secret']);

        $url = WarehouseOAuth::authorizationUrl();

        expect($url)->toContain('access_type=offline')
            ->and($url)->toContain('prompt=consent')
            ->and($url)->toContain(rawurlencode('https://www.googleapis.com/auth/bigquery'))
            ->and($url)->not->toContain('bigquery.readonly')
            ->and($url)->toContain('state=');
    });

    /*
     * The callback arrives from Google, not from wp-admin, so there is no referer to check and
     * `state` is the only CSRF guard. It has to be verified before the code is spent, because
     * exchanging it is the irreversible half.
     */
    test('a callback with a bad state is refused without calling Google', function () {
        Http::fake();
        config(['marketing.warehouse.client_id' => 'cid', 'marketing.warehouse.client_secret' => 'secret']);

        $_GET = ['state' => 'forged', 'code' => 'abc'];

        expect(WarehouseOAuth::completeFromRequest())->toContain('could not be verified');
        Http::assertNothingSent();

        $_GET = [];
    });

    test('a successful exchange stores the refresh token and the account', function () {
        config(['marketing.warehouse.client_id' => 'cid', 'marketing.warehouse.client_secret' => 'secret']);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'openidconnect')) {
                return Http::response(['email' => 'lucas@remoteleverage.com'], 200);
            }

            return Http::response(['refresh_token' => 'refresh-1', 'access_token' => 'ya29'], 200);
        });

        $_GET = ['state' => wp_create_nonce(WarehouseOAuth::CALLBACK_ACTION), 'code' => 'abc'];

        expect(WarehouseOAuth::completeFromRequest())->toBe('')
            ->and(WarehouseOAuth::isConnected())->toBeTrue()
            ->and(WarehouseOAuth::connectedAccount())->toBe('lucas@remoteleverage.com');

        $_GET = [];
    });

    /*
     * A consent that returns an access token and no refresh token leaves the alert working for an
     * hour and then silently failing. It has to be reported as a failure now, not discovered later.
     */
    test('an exchange with no refresh token is a failure', function () {
        config(['marketing.warehouse.client_id' => 'cid', 'marketing.warehouse.client_secret' => 'secret']);

        Http::fake(fn () => Http::response(['access_token' => 'ya29'], 200));

        $_GET = ['state' => wp_create_nonce(WarehouseOAuth::CALLBACK_ACTION), 'code' => 'abc'];

        expect(WarehouseOAuth::completeFromRequest())->toContain('no refresh token')
            ->and(WarehouseOAuth::isConnected())->toBeFalse();

        $_GET = [];
    });

    /* The token is written by the flow and by nothing on the form, so a save must not clear it. */
    test('saving the settings screen does not wipe a stored token', function () {
        config(['marketing.warehouse.client_id' => 'cid', 'marketing.warehouse.client_secret' => 'secret']);

        Http::fake(fn () => Http::response(['refresh_token' => 'refresh-1', 'access_token' => 'ya29'], 200));
        $_GET = ['state' => wp_create_nonce(WarehouseOAuth::CALLBACK_ACTION), 'code' => 'abc'];
        WarehouseOAuth::completeFromRequest();
        $_GET = [];

        (new LeadSettingsService)->save([
            'retention_days' => 30,
            'notification_emails' => '',
        ]);

        expect(WarehouseOAuth::isConnected())->toBeTrue();
    });
});

describe('the BigQuery client', function () {
    /*
     * The REST response separates the schema from the row: values come back as a positional list
     * of `{v: ...}` with no names on them. Zipping them by position against `schema.fields` is what
     * lets the mapper read `$row['cpb_paid']` instead of `$row['f'][18]['v']`, which would start
     * reading the wrong column the day the data team adds one to the SELECT.
     *
     * The fixture is deliberately in a different column order from the SQL file, so a mapper that
     * had quietly learned the positions would fail here.
     */
    test('the schema names are zipped onto the row values by position', function () {
        configureWarehouse();
        fakeBigQuery(bigQueryResponse(array_reverse(warehouseRow(), preserve_keys: true)));

        $day = (new BigQueryClient)->marketingDay();

        expect($day)->not->toBeNull()
            ->and($day->date)->toBe('2026-09-18')
            ->and($day->spend)->toBe(3732.24)
            ->and($day->cpbPaid)->toBe(287.1)
            ->and($day->channels['meta']->spend)->toBe(2892.84);

        // Billed to the configured project, and running the data team's file rather than a copy.
        Http::assertSent(function (Request $request) {
            $body = (array) $request->data();

            return str_contains($request->url(), '/projects/rl-data-platform-dev/queries')
                && ($body['useLegacySql'] ?? true) === false
                && str_contains((string) ($body['query'] ?? ''), 'vw_mkt_home_daily');
        });
    });

    /*
     * A schema and a row of different lengths cannot be zipped, and doing it anyway would shift
     * every column left of the gap onto the wrong field — spend reading as a cost, a date reading
     * as a count — with nothing failing. That is strictly worse than no card, so it is an error.
     */
    test('a schema and a row that do not line up is a failure, not a best effort', function () {
        configureWarehouse();

        $response = bigQueryResponse(warehouseRow());
        $response['schema']['fields'][] = ['name' => 'a_column_the_row_does_not_have', 'type' => 'STRING'];

        fakeBigQuery($response);

        expect((new BigQueryClient)->marketingDay())->toBeNull();
    });

    /*
     * A query that outran `timeoutMs` answers 200 with a job reference and no rows. Read as an
     * empty result it would be a day with no spend and no bookings; it is a query that has not
     * finished. The next hourly run gets it.
     */
    test('an incomplete job is a failure rather than an empty day', function () {
        configureWarehouse();
        fakeBigQuery(bigQueryResponse(warehouseRow(), jobComplete: false));

        expect((new BigQueryClient)->marketingDay())->toBeNull();
    });

    /* No rows is no day. Null makes the card say the cost half is missing; zeros would lie. */
    test('a result with no rows is null, not a day of zeroes', function () {
        configureWarehouse();
        fakeBigQuery(['jobComplete' => true, 'schema' => ['fields' => []], 'rows' => []]);

        expect((new BigQueryClient)->marketingDay())->toBeNull();
    });

    /*
     * Same contract as everything else feeding this alert: a failure returns null and is logged,
     * never thrown. An hourly Slack card must not die because a warehouse answered 403.
     */
    test('an error response returns null rather than throwing', function () {
        configureWarehouse();
        fakeBigQuery(['error' => ['message' => 'Access Denied: Table vw_mkt_home_daily']], 403);

        expect((new BigQueryClient)->marketingDay())->toBeNull();
    });

    /*
     * An environment with no service account is not a broken one — it is every environment but
     * production. It must cost nothing: no signing attempt, no token exchange, no query. The card
     * simply reports the cost half as unavailable.
     */
    test('an unconfigured client makes no request at all', function () {
        Http::fake();

        $client = new BigQueryClient;

        expect($client->isConfigured())->toBeFalse()
            ->and($client->marketingDay())->toBeNull();

        Http::assertNothingSent();
    });

    /* A credential that is not usable JSON is unconfigured, not a crash on the first query. */
    test('a malformed credential is treated as unconfigured', function () {
        Http::fake();
        config(['marketing.warehouse.credentials' => 'not json at all']);

        expect((new BigQueryClient)->isConfigured())->toBeFalse();

        Http::assertNothingSent();
    });

    /*
     * Lucas's existing automation authenticates as a user, not a service account — so the client
     * has to take either. `type` decides which grant is sent; nothing is configured to say so.
     */
    test('a user credential uses a refresh-token grant, not a signed assertion', function () {
        Cache::forget('rl_bigquery_access_token');

        config([
            'marketing.warehouse.credentials' => '',
            'marketing.warehouse.client_id' => 'cid.apps.googleusercontent.com',
            'marketing.warehouse.client_secret' => 'secret',
            'marketing.warehouse.refresh_token' => 'refresh',
        ]);
        config(['marketing.warehouse.project_id' => 'rl-data-platform-dev']);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'ya29-user'], 200);
            }

            return Http::response([
                'jobComplete' => true,
                'schema' => ['fields' => [['name' => 'total_spend']]],
                'rows' => [['f' => [['v' => '3732.24']]]],
            ], 200);
        });

        expect((new BigQueryClient)->isConfigured())->toBeTrue()
            ->and((new BigQueryClient)->marketingDay()?->spend)->toBe(3732.24);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'oauth2.googleapis.com')) {
                return true;
            }

            $body = (string) $request->body();

            return str_contains($body, 'grant_type=refresh_token')
                && ! str_contains($body, 'jwt-bearer');
        });
    });

    /* A half-filled OAuth credential is not a credential. */
    test('an incomplete OAuth trio is unconfigured', function () {
        config([
            'marketing.warehouse.credentials' => '',
            'marketing.warehouse.client_id' => 'cid',
            'marketing.warehouse.client_secret' => 'secret',
            'marketing.warehouse.refresh_token' => '',
        ]);

        expect((new BigQueryClient)->isConfigured())->toBeFalse();
    });

    test('a service account key with no private key is unconfigured', function () {
        config(['marketing.warehouse.credentials' => json_encode(['type' => 'service_account', 'client_email' => 'a@b'])]);

        expect((new BigQueryClient)->isConfigured())->toBeFalse();
    });
});
