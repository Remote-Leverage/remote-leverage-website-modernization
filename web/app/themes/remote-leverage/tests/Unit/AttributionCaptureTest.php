<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\AttributionCollector;
use Illuminate\Http\Request;

/*
 * Attribution capture, ported from the legacy Gravity Form (form 20).
 *
 * The form carried ~45 hidden fields; v2 captured 11. These tests pin the two properties that
 * matter beyond "does it read a parameter":
 *
 *  - a parameter this code has never heard of is still captured, so a new ad platform's click
 *    id is not lost between the day marketing starts using it and the day someone notices;
 *  - the HubSpot-mapped set is complete, because HubSpot rejects the entire request when a
 *    property does not exist, and a missing one is only discovered on the first real sync.
 */

function attributionRequest(array $query = [], array $cookies = [], array $server = []): Request
{
    return Request::create('https://remoteleverage.com/hire-va-4/', 'GET', $query, $cookies, [], $server);
}

describe('AttributionCollector', function () {
    test('reads every named parameter from the query string', function () {
        $collected = (new AttributionCollector)->collect(attributionRequest([
            'utm_source' => 'linkedin',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'va-q4',
            'utm_term' => 'virtual assistant',
            'utm_content' => 'variant-b',
            'utm_id' => '12345',
            'gclid' => 'GCL123',
            'fbclid' => 'FB456',
            'li_fat_id' => 'LI789',
            '_fbc' => 'fb.1.123.456',
            'oppref' => 'OPP1',
            'partner' => 'oyster',
            'scheduler_link' => 'https://calendly.com/d/abc',
            'handl_landing_page_base' => 'https://remoteleverage.com/hire-va-4/',
        ]));

        expect($collected['named'])->toHaveCount(14)
            ->and($collected['named']['utm_source'])->toBe('linkedin')
            ->and($collected['named']['li_fat_id'])->toBe('LI789')
            ->and($collected['named']['fbc'])->toBe('fb.1.123.456')
            ->and($collected['named']['partner'])->toBe('oyster');
    });

    test('falls back to the HandL cookie when the URL no longer carries the parameter', function () {
        // HandL writes on the landing page; the visitor may convert several pages later.
        $collected = (new AttributionCollector)->collect(
            attributionRequest([], ['handl_utm_source' => 'google', 'handl_utm_campaign' => 'brand'])
        );

        expect($collected['named']['utm_source'])->toBe('google')
            ->and($collected['named']['utm_campaign'])->toBe('brand');
    });

    test('the query string wins over the cookie', function () {
        $collected = (new AttributionCollector)->collect(
            attributionRequest(['utm_source' => 'bing'], ['handl_utm_source' => 'google'])
        );

        expect($collected['named']['utm_source'])->toBe('bing');
    });

    test('an unknown query parameter is captured rather than dropped', function () {
        // The whole point: a platform we have never integrated shows up tomorrow.
        $collected = (new AttributionCollector)->collect(attributionRequest([
            'ttclid' => 'TIKTOK-123',
            'some_future_id' => 'XYZ',
        ]));

        expect($collected['attribution']['unmapped'])->toBe([
            'ttclid' => 'TIKTOK-123',
            'some_future_id' => 'XYZ',
        ]);
    });

    test('the HandL first-touch set is recorded without needing a column', function () {
        $collected = (new AttributionCollector)->collect(attributionRequest([
            'first_utm_source' => 'reddit',
            'msclkid' => 'MS1',
            'gbraid' => 'GB1',
            'organic_source' => 'google.com',
        ]));

        expect($collected['attribution']['handl'])
            ->toHaveKeys(['first_utm_source', 'msclkid', 'gbraid', 'organic_source'])
            ->and($collected['attribution']['unmapped'] ?? [])->toBe([]);
    });

    test('routing and referral parameters are not treated as attribution noise', function () {
        // `via` is already read into referral_code by the Referral domain; `page` is WordPress.
        $collected = (new AttributionCollector)->collect(attributionRequest([
            'via' => 'partner-x',
            'page' => '2',
            'preview' => 'true',
        ]));

        expect($collected['attribution']['unmapped'] ?? [])->toBe([]);
    });

    test('empty and whitespace-only values are not stored', function () {
        $collected = (new AttributionCollector)->collect(attributionRequest([
            'utm_source' => '',
            'utm_medium' => '   ',
            'utm_campaign' => 'real',
        ]));

        expect($collected['named'])->toBe(['utm_campaign' => 'real']);
    });

    test('a hostile value is truncated rather than stored whole', function () {
        $collected = (new AttributionCollector)->collect(attributionRequest([
            'utm_source' => str_repeat('a', 5000),
        ]));

        expect(strlen($collected['named']['utm_source']))->toBeLessThanOrEqual(500);
    });

    test('unmapped parameters are capped so one URL cannot bloat the row', function () {
        $query = [];
        for ($i = 0; $i < 60; $i++) {
            $query["unknown_{$i}"] = "v{$i}";
        }

        $collected = (new AttributionCollector)->collect(attributionRequest($query));

        expect(count($collected['attribution']['unmapped']))->toBeLessThanOrEqual(25);
    });

    test('the client IP comes from the forwarded header, not the CDN edge', function () {
        // Behind CloudFront/Cloudflare REMOTE_ADDR is the edge node, shared by thousands.
        $ip = (new AttributionCollector)->ipAddress(attributionRequest([], [], [
            'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 70.132.1.1',
            'REMOTE_ADDR' => '70.132.1.1',
        ]));

        expect($ip)->toBe('203.0.113.7');
    });

    test('no request means no attribution, not an error', function () {
        $collected = (new AttributionCollector)->collect(null);

        expect($collected)->toBe(['named' => [], 'attribution' => []]);
    });
});

describe('HubSpot field coverage', function () {
    test('every HubSpot-mapped field has a way to be collected', function () {
        // The mapping supplied on 2026-09-16, as HubSpot property => Lead column. A column that
        // nothing can populate is the failure this catches: the sync would silently send
        // nothing for it, which looks identical to "the visitor had no campaign".
        $mapped = [
            'utm_source' => 'utm_source',
            'utm_medium' => 'utm_medium',
            'utm_campaign' => 'utm_campaign',
            'utm_term' => 'utm_term',
            'utm_content' => 'utm_content',
            'utm_id' => 'utm_id',
            'gclid' => 'gclid',
            'fbclid' => 'fbclid',
            'fbc' => 'fbc',
            'li_fat_id' => 'li_fat_id',
            'oppref' => 'oppref',
            'partner_name' => 'partner',
            'schedule_link' => 'scheduler_link',
            'landing_page' => 'landing_page_base',
        ];

        $collectable = array_keys(AttributionCollector::NAMED);

        $uncollectable = [];

        foreach ($mapped as $hubspotProperty => $column) {
            if (! in_array($column, $collectable, true)) {
                $uncollectable[] = "{$hubspotProperty} <- {$column}";
            }
        }

        // Named so a failure says which mapping broke, not just that one did.
        expect($uncollectable)->toBe([]);
    });

    test('the gateway sends no rl_* property, since none exist in the portal', function () {
        // Verified against the portal's 486 contact properties on 2026-09-16: zero rl_* exist.
        // HubSpot rejects the whole request on an unknown property, so one of these would take
        // every sync down, not just its own field.
        $source = file_get_contents(__DIR__.'/../../app/Domains/Lead/Services/HubSpotGateway.php');

        expect($source)->not->toContain("'rl_");
    });
});

describe('PostHog session replay link', function () {
    test('builds a link from PostHog own session id', function () {
        config([
            'services.posthog.project_id' => '282594',
            'services.posthog.app_host' => 'https://us.posthog.com',
        ]);

        $lead = new Lead;
        $lead->forceFill(['posthog_session_id' => '0199abc-de-f0', 'session_id' => 'our-own-uuid']);

        expect($lead->posthogReplayUrl())
            ->toBe('https://us.posthog.com/project/282594/replay/0199abc-de-f0');
    });

    test('returns null rather than a dead link when PostHog never gave us a session', function () {
        // A broken admin link reads as a PostHog outage; no link reads as no recording.
        config(['services.posthog.project_id' => '282594']);

        $lead = new Lead;
        $lead->forceFill(['posthog_session_id' => null, 'session_id' => 'our-own-uuid']);

        expect($lead->posthogReplayUrl())->toBeNull();
    });

    test('returns null when the project id is not configured', function () {
        config(['services.posthog.project_id' => '']);

        $lead = new Lead;
        $lead->forceFill(['posthog_session_id' => 'abc123']);

        expect($lead->posthogReplayUrl())->toBeNull();
    });

    test('never builds the link from our own session_id', function () {
        // The two ids are unrelated; ours means nothing to PostHog and would always 404.
        config(['services.posthog.project_id' => '282594']);

        $lead = new Lead;
        $lead->forceFill(['posthog_session_id' => 'posthog-side', 'session_id' => 'ours-must-not-appear']);

        expect($lead->posthogReplayUrl())->not->toContain('ours-must-not-appear');
    });

    test('falls back to an email search when there is no session id', function () {
        // The admin card renders this instead of vanishing: most leads carry no session id,
        // and no card reads as "PostHog has nothing on this person" rather than "we do not
        // know which session was theirs".
        config(['services.posthog.project_id' => '282594']);

        $lead = new Lead;
        $lead->forceFill(['posthog_session_id' => null, 'email' => 'someone+tag@example.com']);

        expect($lead->posthogPersonUrl())
            ->toBe('https://us.posthog.com/project/282594/persons?q=someone%2Btag%40example.com');
    });

    test('has no person search to offer without an email', function () {
        config(['services.posthog.project_id' => '282594']);

        $lead = new Lead;
        $lead->forceFill(['email' => '']);

        expect($lead->posthogPersonUrl())->toBeNull();
    });
});

/*
 * Meta match-quality fields, added 2026-09-18.
 *
 * The first production lead through the Conversions API reported `fbp: false` and no stored
 * `fbc`, which is the difference between a conversion Meta can attribute to an ad and one it
 * counts anonymously. Both were capture-side.
 */
describe('AttributionCollector Meta identifiers', function () {
    test('derives fbc from a bare fbclid when the _fbc cookie does not exist yet', function () {
        $before = (int) round(microtime(true) * 1000);

        $collected = (new AttributionCollector)->collect(attributionRequest(['fbclid' => 'IwAR-abc123']));

        $after = (int) round(microtime(true) * 1000);

        expect($collected['named']['fbc'])->toMatch('/^fb\.1\.\d+\.IwAR-abc123$/');

        // `fb.<subdomainIndex>.<creationTimeMs>.<fbclid>` — the timestamp is the click time, and
        // a visitor landing from an ad is clicking now.
        $ms = (int) explode('.', $collected['named']['fbc'])[2];
        expect($ms)->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual($after);
    });

    test('a real _fbc cookie always wins over a derived one', function () {
        $collected = (new AttributionCollector)->collect(
            attributionRequest(['fbclid' => 'IwAR-abc123'], ['_fbc' => 'fb.1.1699999999999.realcookie'])
        );

        expect($collected['named']['fbc'])->toBe('fb.1.1699999999999.realcookie');
    });

    test('leaves fbc alone when there is no fbclid to derive from', function () {
        $collected = (new AttributionCollector)->collect(attributionRequest(['utm_source' => 'google']));

        expect($collected['named'])->not->toHaveKey('fbc');
    });

    test('reads _fbp and _fbc from the raw cookie jar when the Request bag does not carry them', function () {
        // Lead capture runs from a Livewire XHR and from WordPress hooks, where the Request is
        // not always built from the live superglobals — and cookie decryption drops a
        // third-party cookie rather than passing it through. Both of these are written by Meta's
        // pixel JS, so they are exactly the ones Laravel never knows about.
        $original = $_COOKIE;
        $_COOKIE['_fbp'] = 'fb.1.1700000000000.987654321';
        $_COOKIE['_fbc'] = 'fb.1.1700000000000.IwAR-from-jar';

        try {
            $collected = (new AttributionCollector)->collect(attributionRequest());

            expect($collected['attribution']['handl']['_fbp'])->toBe('fb.1.1700000000000.987654321')
                ->and($collected['named']['fbc'])->toBe('fb.1.1700000000000.IwAR-from-jar');
        } finally {
            $_COOKIE = $original;
        }
    });
});
