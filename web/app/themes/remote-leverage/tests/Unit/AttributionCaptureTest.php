<?php

declare(strict_types=1);

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
