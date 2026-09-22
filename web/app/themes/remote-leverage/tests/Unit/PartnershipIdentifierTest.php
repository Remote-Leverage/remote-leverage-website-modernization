<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\AttributionCollector;
use App\Domains\Lead\Services\HubSpotGateway;
use App\Domains\PartnerHub\Support\PartnerLink;
use App\Domains\Referral\Support\ReferralLink;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * WR-73 — the partnership identifier, end to end.
 *
 * Two halves that only work as a pair: the Partner Hub emits a link carrying the partner code,
 * and the HubSpot payload turns that code into `partnership_id` on the contact. Testing either
 * alone would let the other drift — a link nobody reads, or a property nothing populates.
 */
beforeEach(function () {
    // Same reasoning as HubSpotLifecycleSyncTest: the Http facade caches its Factory for the
    // life of the process, so without a hard swap a later test inherits earlier stubs.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);

    $GLOBALS['_wp_mock_options']['rl_lead_settings'] = [
        'hubspot_access_token' => 'pat-test-token',
        'hubspot_portal_id' => '243484989',
    ];
    $GLOBALS['_wp_mock_posts'] = [];
    $GLOBALS['_wp_mock_post_meta'] = [];
    $GLOBALS['_wp_mock_transients'] = [];

    PartnerLink::flush();
});

/**
 * Seed an `rl_partner` the way the CPT stores one.
 */
function seedPartner(int $postId, string $code, string $name): void
{
    $GLOBALS['_wp_mock_posts'][$postId] = [
        'ID' => $postId,
        'post_type' => 'rl_partner',
        'post_status' => 'publish',
        'post_title' => $name,
        'post_name' => Str::slug($name),
    ];

    update_post_meta($postId, PartnerLink::META_KEY, $code);
    update_post_meta($postId, '_rl_partner_name', $name);

    PartnerLink::flush();
}

function fakeHubSpotContactSchema(array $names): void
{
    $GLOBALS['_wp_mock_transients'] = [];

    Http::fake([
        'api.hubapi.com/crm/v3/properties/contacts*' => Http::response([
            'results' => array_map(static fn (string $n): array => ['name' => $n], $names),
        ], 200),
    ]);
}

function partnershipPropertiesFor(Lead $lead): array
{
    $method = new ReflectionMethod(HubSpotGateway::class, 'propertiesFor');
    $method->setAccessible(true);

    return $method->invoke(new HubSpotGateway, $lead);
}

function partnerLead(?string $partner): Lead
{
    return Lead::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Partner Sourced',
        'first_name' => 'Partner',
        'last_name' => 'Sourced',
        'email' => 'partner-sourced+'.Str::random(6).'@example.com',
        'partner' => $partner,
        'source_type' => 'partnership',
        'status' => 'captured',
    ]);
}

describe('PartnerLink (the hub half)', function () {
    test('builds a tracked link carrying the partner code on the partner parameter', function () {
        expect(PartnerLink::for('RL-OYSTER'))
            ->toBe('https://remoteleverage.com/'.ReferralLink::DESTINATION_PATH.'/?partner=RL-OYSTER');
    });

    test('a code with characters needing encoding is escaped, not concatenated raw', function () {
        expect(PartnerLink::for('RL OYSTER&x=1'))
            ->toContain('?partner=RL%20OYSTER%26x%3D1')
            ->and(PartnerLink::for('RL OYSTER&x=1'))->not->toContain('&x=1');
    });

    test('an arbitrary path falls back to the default destination', function () {
        // The path reaches this from the page, so it is untrusted in exactly the way
        // ReferralLink::isAllowed() exists to handle. A partner must not be able to
        // hand out a link to a retired page — or to another site.
        expect(PartnerLink::for('RL-OYSTER', 'not-a-real-page'))
            ->toBe('https://remoteleverage.com/'.ReferralLink::DESTINATION_PATH.'/?partner=RL-OYSTER');
    });

    test('an allowed path is honoured, and the homepage renders as a bare root', function () {
        expect(PartnerLink::for('RL-OYSTER', 'hire-va'))
            ->toBe('https://remoteleverage.com/hire-va/?partner=RL-OYSTER')
            ->and(PartnerLink::for('RL-OYSTER', ''))
            ->toBe('https://remoteleverage.com/?partner=RL-OYSTER');
    });

    test('resolves a code to its partner, and an unknown code to null', function () {
        seedPartner(4101, 'RL-OYSTER', 'Oyster');

        expect(PartnerLink::nameForCode('RL-OYSTER'))->toBe('Oyster')
            ->and(PartnerLink::nameForCode('SPRING-CAMPAIGN'))->toBeNull()
            ->and(PartnerLink::nameForCode(''))->toBeNull();
    });

    test('a draft partner does not resolve', function () {
        seedPartner(4102, 'RL-LEXGO', 'LexGo');
        $GLOBALS['_wp_mock_posts'][4102]['post_status'] = 'draft';
        PartnerLink::flush();

        expect(PartnerLink::nameForCode('RL-LEXGO'))->toBeNull();
    });
});

describe('the HubSpot half', function () {
    test('a code from a hub link becomes partnership_id, with the partner name beside it', function () {
        fakeHubSpotContactSchema(['email', 'firstname', 'lastname', 'partnership_id', 'partner_name']);
        seedPartner(4103, 'RL-OYSTER', 'Oyster');

        $properties = partnershipPropertiesFor(partnerLead('RL-OYSTER'));

        expect($properties['partnership_id'])->toBe('RL-OYSTER')
            // The readable name, not the code repeated — the two fields carry different facts.
            ->and($properties['partner_name'])->toBe('Oyster');
    });

    test('a legacy campaign tag stays a label and is not promoted to an id', function () {
        // `?partner=` carried free text long before it carried codes. A value that matches no
        // partner must not become an identifier nothing can join on.
        fakeHubSpotContactSchema(['email', 'firstname', 'lastname', 'partnership_id', 'partner_name']);

        $properties = partnershipPropertiesFor(partnerLead('Spring Campaign'));

        expect($properties)->not->toHaveKey('partnership_id')
            ->and($properties['partner_name'])->toBe('Spring Campaign');
    });

    test('no partner at all sends neither field', function () {
        fakeHubSpotContactSchema(['email', 'firstname', 'lastname', 'partnership_id', 'partner_name']);

        $properties = partnershipPropertiesFor(partnerLead(null));

        expect($properties)->not->toHaveKey('partnership_id')
            ->and($properties)->not->toHaveKey('partner_name');
    });

    test('partnership_id is dropped rather than 400ing the sync while the portal lacks it', function () {
        // The state this shipped in: checked against portal 243484989 on 2026-09-21 and the
        // property did not exist. One unknown property rejects the *entire* request, so the
        // safe failure mode is the whole point of shipping before the property is created.
        fakeHubSpotContactSchema(['email', 'firstname', 'lastname', 'partner_name']);
        seedPartner(4104, 'RL-OYSTER', 'Oyster');

        $properties = partnershipPropertiesFor(partnerLead('RL-OYSTER'));

        expect($properties)->not->toHaveKey('partnership_id')
            // Everything else still goes, which is what makes the no-op safe.
            ->and($properties['partner_name'])->toBe('Oyster')
            ->and($properties)->toHaveKey('email');
    });
});

describe('the two halves agree', function () {
    test('the parameter the hub link emits is the one AttributionCollector stamps', function () {
        // If these ever diverge the link still works and the lead still saves, but the
        // partnership is silently lost — no error, just an empty column.
        expect(PartnerLink::PARAM)
            ->toBe('partner')
            ->and(AttributionCollector::NAMED)
            ->toHaveKey(PartnerLink::PARAM);
    });

    test('the hub parameter is not on the ignore list', function () {
        expect(AttributionCollector::IGNORED)
            ->not->toContain(PartnerLink::PARAM);
    });
});
