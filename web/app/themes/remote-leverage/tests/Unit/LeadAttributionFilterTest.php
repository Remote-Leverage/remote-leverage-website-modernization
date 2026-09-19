<?php

declare(strict_types=1);

use App\Domains\Lead\Data\LeadAudience;
use App\Domains\Lead\Export\LeadExportOptions;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadPlatform;

/*
 * Filtering the leads list by platform, and by whether a lead looks like a VA applicant.
 *
 * The risk in both is the same and it is not that the filter fails loudly. A platform filter
 * that knows `facebook` but not `fb` or `ig`, or a VA filter whose SQL has drifted from the
 * badge's PHP, returns a shorter list that looks complete. Nothing about the screen says rows
 * are missing, so the guard has to be a test rather than a careful reading.
 */

function attributionLead(array $attributes = []): Lead
{
    static $sequence = 0;
    $sequence++;

    return Lead::create(array_merge([
        'uuid' => 'attr-'.$sequence,
        'name' => 'Attr '.$sequence,
        'email' => 'attr'.$sequence.'@example.com',
        'status' => 'captured',
        'source_type' => 'organic',
    ], $attributes));
}

beforeEach(function () {
    Lead::truncate();
});

test('a platform filter gathers every spelling of that platform', function () {
    // The three spellings Meta actually arrives under in production, plus one that must not match.
    attributionLead(['utm_source' => 'facebook']);
    attributionLead(['utm_source' => 'fb']);
    attributionLead(['utm_source' => 'ig']);
    attributionLead(['utm_source' => 'adwords']);

    $query = Lead::query();
    LeadPlatform::apply($query, 'meta');

    expect($query->count())->toBe(3);
});

test('platform matching ignores case and stray whitespace', function () {
    attributionLead(['utm_source' => 'Facebook']);
    attributionLead(['utm_source' => ' facebook ']);

    $query = Lead::query();
    LeadPlatform::apply($query, 'meta');

    expect($query->count())->toBe(2);
});

test('direct covers both an empty utm_source and a null one', function () {
    attributionLead(['utm_source' => null]);
    attributionLead(['utm_source' => '']);
    attributionLead(['utm_source' => 'facebook']);

    $query = Lead::query();
    LeadPlatform::apply($query, LeadPlatform::DIRECT);

    expect($query->count())->toBe(2);
});

test('other catches a tagged source that belongs to no known platform', function () {
    attributionLead(['utm_source' => 'some-newsletter']);
    attributionLead(['utm_source' => 'facebook']);
    attributionLead(['utm_source' => null]);

    $query = Lead::query();
    LeadPlatform::apply($query, LeadPlatform::OTHER);

    expect($query->pluck('utm_source')->all())->toBe(['some-newsletter']);
});

test('an empty or unrecognised platform is a no-op rather than an empty list', function () {
    attributionLead(['utm_source' => 'facebook']);
    attributionLead(['utm_source' => null]);

    foreach (['', 'not-a-platform', '   '] as $slug) {
        $query = Lead::query();
        LeadPlatform::apply($query, $slug);

        expect($query->count())->toBe(2);
    }
});

test('every platform bucket is reachable from the dropdown and sums to the whole table', function () {
    foreach (['facebook', 'fb', 'ig', 'adwords', 'bing', 'customerio', 'chatgpt.com', 'trustpilot', 'linkedin', 'mystery', null] as $source) {
        attributionLead(['utm_source' => $source]);
    }

    $counted = 0;

    foreach (array_keys(LeadPlatform::options()) as $slug) {
        $query = Lead::query();
        LeadPlatform::apply($query, $slug);
        $counted += $query->count();
    }

    // No lead falls between the buckets, and none is counted by two of them.
    expect($counted)->toBe(Lead::count());
});

/*
 * The click-ID fallback, added 2026-09-19.
 *
 * The risk it introduces is not that it fails to match. It is that it matches *twice*: a lead
 * carrying both a `gclid` and an `fbclid` counted under Google and again under Meta inflates
 * both booking counts, and the cost alert divides ad spend by those counts. The partition test
 * below is the guard, and it is why the fallback carries a precedence order at all.
 */

test('a click id attributes a lead that lost its utm_source', function () {
    attributionLead(['utm_source' => null, 'gclid' => 'GCL-1']);
    attributionLead(['utm_source' => '', 'msclkid' => 'MS-1']);
    attributionLead(['utm_source' => null, 'fbclid' => 'FB-1']);

    foreach (['google' => 'GCL-1', 'microsoft' => 'MS-1', 'meta' => 'FB-1'] as $slug => $expected) {
        $query = Lead::query();
        LeadPlatform::apply($query, $slug);

        expect($query->count())->toBe(1, "{$slug} should claim the lead carrying {$expected}");
    }
});

test('a recognised utm_source beats a conflicting click id', function () {
    // Tagged Meta, but carrying a Google click id. The tag is an explicit statement and wins.
    attributionLead(['utm_source' => 'facebook', 'gclid' => 'GCL-2']);

    $meta = Lead::query();
    LeadPlatform::apply($meta, 'meta');

    $google = Lead::query();
    LeadPlatform::apply($google, 'google');

    expect($meta->count())->toBe(1)
        ->and($google->count())->toBe(0);
});

test('a click id rescues a lead whose tagged source belongs to no platform', function () {
    // `other` used to swallow this. It is a paid Google click with a hand-written tag on it.
    attributionLead(['utm_source' => 'some-newsletter', 'gclid' => 'GCL-3']);

    $google = Lead::query();
    LeadPlatform::apply($google, 'google');

    $other = Lead::query();
    LeadPlatform::apply($other, LeadPlatform::OTHER);

    expect($google->count())->toBe(1)
        ->and($other->count())->toBe(0);
});

test('a lead carrying two click ids is claimed by exactly one platform', function () {
    // fbclid is last in the precedence order because Meta stamps it on organic clicks too.
    attributionLead(['utm_source' => null, 'gclid' => 'GCL-4', 'fbclid' => 'FB-4']);

    $google = Lead::query();
    LeadPlatform::apply($google, 'google');

    $meta = Lead::query();
    LeadPlatform::apply($meta, 'meta');

    expect($google->count())->toBe(1)
        ->and($meta->count())->toBe(0);
});

test('direct and other no longer swallow a lead that carries a click id', function () {
    attributionLead(['utm_source' => null, 'gclid' => 'GCL-5']);
    attributionLead(['utm_source' => 'some-newsletter', 'fbclid' => 'FB-5']);
    attributionLead(['utm_source' => null]);
    attributionLead(['utm_source' => 'some-newsletter']);

    $direct = Lead::query();
    LeadPlatform::apply($direct, LeadPlatform::DIRECT);

    $other = Lead::query();
    LeadPlatform::apply($other, LeadPlatform::OTHER);

    // One of each: the two untagged, click-id-less leads, and nothing more.
    expect($direct->count())->toBe(1)
        ->and($other->count())->toBe(1);
});

test('the buckets still partition the table once click ids are in play', function () {
    $shapes = [
        ['utm_source' => 'facebook'],
        ['utm_source' => 'adwords'],
        ['utm_source' => null],
        ['utm_source' => 'mystery'],
        ['utm_source' => null, 'gclid' => 'G1'],
        ['utm_source' => null, 'fbclid' => 'F1'],
        ['utm_source' => null, 'msclkid' => 'M1'],
        ['utm_source' => null, 'li_fat_id' => 'L1'],
        ['utm_source' => null, 'gclid' => 'G2', 'fbclid' => 'F2'],
        ['utm_source' => null, 'msclkid' => 'M2', 'fbclid' => 'F3'],
        ['utm_source' => 'mystery', 'gclid' => 'G3'],
        ['utm_source' => 'facebook', 'gclid' => 'G4'],
    ];

    foreach ($shapes as $shape) {
        attributionLead($shape);
    }

    $counted = 0;

    foreach (array_keys(LeadPlatform::options()) as $slug) {
        $query = Lead::query();
        LeadPlatform::apply($query, $slug);
        $counted += $query->count();
    }

    expect($counted)->toBe(Lead::count());
});

test('the click-id filter and the click-id badge classify every lead the same way', function () {
    $shapes = [
        ['utm_source' => 'facebook'],
        ['utm_source' => 'ADWORDS'],
        ['utm_source' => ' bing '],
        ['utm_source' => null],
        ['utm_source' => ''],
        ['utm_source' => 'mystery'],
        ['utm_source' => null, 'gclid' => 'G1'],
        ['utm_source' => '', 'fbclid' => 'F1'],
        ['utm_source' => null, 'msclkid' => 'M1'],
        ['utm_source' => null, 'li_fat_id' => 'L1'],
        ['utm_source' => null, 'gclid' => 'G2', 'fbclid' => 'F2'],
        ['utm_source' => null, 'li_fat_id' => 'L2', 'fbclid' => 'F4'],
        ['utm_source' => 'mystery', 'msclkid' => 'M2'],
        ['utm_source' => 'facebook', 'gclid' => 'G3'],
        // Whitespace-only click ids are not click ids.
        ['utm_source' => null, 'gclid' => '   '],
    ];

    foreach ($shapes as $shape) {
        $lead = attributionLead($shape);

        $badge = LeadPlatform::for($lead);

        $query = Lead::query()->where('id', $lead->id);
        LeadPlatform::apply($query, $badge);

        expect($query->count())->toBe(
            1,
            'lead '.$lead->id.' is badged '.$badge.' but the '.$badge.' filter does not return it',
        );
    }
});

test('resolution says whether a platform came from the tag or the click id', function () {
    $tagged = attributionLead(['utm_source' => 'facebook']);
    $rescued = attributionLead(['utm_source' => null, 'gclid' => 'G9']);
    $neither = attributionLead(['utm_source' => 'mystery']);

    expect(LeadPlatform::resolution($tagged))->toBe(LeadPlatform::BY_UTM)
        ->and(LeadPlatform::resolution($rescued))->toBe(LeadPlatform::BY_CLICK_ID)
        ->and(LeadPlatform::resolution($neither))->toBeNull();
});

/*
 * The parity matrix.
 *
 * Every row is a lead shape the heuristic has an opinion about. The test does not assert what
 * that opinion should be — LeadDomainTest already pins the rules — it asserts that the badge and
 * the filter hold the same one, which is the thing a second implementation can quietly break.
 */
test('the VA filter and the VA badge classify every lead the same way', function () {
    $shapes = [
        // Plain domestic and foreign, by ISO-2.
        ['phone_country' => 'US', 'phone' => '+13055551234'],
        ['phone_country' => 'CA', 'phone' => '+16045551234'],
        ['phone_country' => 'PH', 'phone' => '+639171234567'],
        ['phone_country' => 'CO', 'phone' => '+573001234567'],
        // Lowercase and padded ISO-2: strtoupper(trim()) in PHP, UPPER(TRIM()) in SQL.
        ['phone_country' => 'us', 'phone' => '+13055551234'],
        ['phone_country' => ' ph ', 'phone' => '+639171234567'],
        // No ISO-2 at all, so the dial-code fallback decides.
        ['phone_country' => null, 'phone' => '+13055551234'],
        ['phone_country' => '', 'phone' => '+639171234567'],
        ['phone_country' => '', 'phone' => '3055551234'],
        ['phone_country' => '', 'phone' => ''],
        ['phone_country' => null, 'phone' => null],
        ['phone_country' => '', 'phone' => ' +44 20 7946 0958'],
        // Referred leads are never flagged, however foreign the phone.
        ['phone_country' => 'PH', 'phone' => '+639171234567', 'referral_code' => 'ABC123'],
        ['phone_country' => 'PH', 'phone' => '+639171234567', 'source_type' => 'referral_hub'],
        ['phone_country' => 'PH', 'phone' => '+639171234567', 'source_type' => 'partnership'],
        // A blank code is not a referral. (source_type is NOT NULL DEFAULT 'organic', so there
        // is no null case to cover here — the SQL guards one anyway, see LeadAudience.)
        ['phone_country' => 'PH', 'phone' => '+639171234567', 'referral_code' => '   '],
        ['phone_country' => 'PH', 'phone' => '+639171234567', 'source_type' => 'ad'],
    ];

    foreach ($shapes as $shape) {
        attributionLead($shape);
    }

    $byPhp = Lead::all()
        ->filter(fn (Lead $lead) => $lead->audience()->possibleVirtualAssistant)
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $vaQuery = Lead::query();
    LeadAudience::constrain($vaQuery, 'va');
    $bySql = $vaQuery->pluck('id')->sort()->values()->all();

    expect($bySql)->toBe($byPhp);

    // The two sides must also partition the table: no lead in both, none in neither.
    $clientQuery = Lead::query();
    LeadAudience::constrain($clientQuery, 'clients');

    expect($clientQuery->count() + count($bySql))->toBe(Lead::count())
        ->and(array_intersect($clientQuery->pluck('id')->all(), $bySql))->toBe([]);
});

test('excluding possible VAs keeps every lead the badge does not flag', function () {
    attributionLead(['phone_country' => 'US', 'phone' => '+13055551234']);
    attributionLead(['phone_country' => 'PH', 'phone' => '+639171234567']);

    $query = Lead::query();
    LeadAudience::constrain($query, 'clients');

    expect($query->pluck('phone_country')->all())->toBe(['US']);
});

test('an unrecognised audience mode leaves the list alone', function () {
    attributionLead(['phone_country' => 'US']);
    attributionLead(['phone_country' => 'PH', 'phone' => '+639171234567']);

    foreach (['', 'everyone', 'nonsense'] as $mode) {
        $query = Lead::query();
        LeadAudience::constrain($query, $mode);

        expect($query->count())->toBe(2);
    }
});

/*
 * Export parity.
 *
 * LeadExportOptions already carried status, source type, dates and the search term so that an
 * export started from a filtered list covers what the list was showing. These two axes have to
 * keep that promise or the CSV quietly widens past the screen it was launched from.
 */
test('the export honours the platform and audience filters the list was showing', function () {
    attributionLead(['utm_source' => 'facebook', 'phone_country' => 'US', 'phone' => '+13055551234']);
    attributionLead(['utm_source' => 'ig', 'phone_country' => 'PH', 'phone' => '+639171234567']);
    attributionLead(['utm_source' => 'adwords', 'phone_country' => 'US', 'phone' => '+13055551234']);

    $options = LeadExportOptions::fromRequest([
        'platform' => 'meta',
        'audience' => 'clients',
    ]);

    expect($options->query()->pluck('utm_source')->all())->toBe(['facebook']);
});

test('export options survive the round trip through the job store', function () {
    $options = LeadExportOptions::fromRequest([
        'platform' => 'meta',
        'audience' => 'va',
        'status' => 'booked',
    ]);

    // The job is resumed from this array between batches; a field that does not survive it is a
    // filter that silently widens halfway through a large export.
    $resumed = LeadExportOptions::fromArray($options->toArray());

    expect($resumed->platform)->toBe('meta')
        ->and($resumed->audience)->toBe('va')
        ->and($resumed->status)->toBe('booked');
});

test('the export rejects a platform or audience it does not recognise', function () {
    $options = LeadExportOptions::fromRequest([
        'platform' => "meta'; DROP TABLE wp_rl_leads; --",
        'audience' => 'everyone',
    ]);

    expect($options->platform)->toBe('')
        ->and($options->audience)->toBe('');
});

test('the export sentence names both filters', function () {
    $options = LeadExportOptions::fromRequest(['platform' => 'meta', 'audience' => 'clients']);

    expect($options->describe())
        ->toContain('platform Meta (Facebook / Instagram)')
        ->toContain('excluding possible VAs');
});
