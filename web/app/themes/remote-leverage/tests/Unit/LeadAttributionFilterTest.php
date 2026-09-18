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
