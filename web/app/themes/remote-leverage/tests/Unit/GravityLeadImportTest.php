<?php

declare(strict_types=1);

use App\Domains\Lead\Import\GravityCsvReader;
use App\Domains\Lead\Import\GravityLeadImporter;
use App\Domains\Lead\Import\GravityLeadMapper;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\IdentityResolver;
use App\Domains\Referral\Services\AttributionEngine;
use Illuminate\Support\Str;

/**
 * Covers the Gravity Forms backfill.
 *
 * Every assertion here stands for something that failed silently rather than loudly during the
 * first run of this import against the real 7,397-entry export. That is the shape of the whole
 * risk: a CSV backfill does not crash when it is wrong, it writes plausible rows.
 */

/**
 * Build a Gravity-shaped export on disk.
 *
 * Deliberately reproduces the file's quirks rather than clean CSV: the BOM before the first
 * opening quote, `&amp;` for every ampersand, a backslash inside a value, and a literal newline
 * inside the consent paragraph.
 *
 * @param  array<int, array<string, string>>  $rows
 */
function gravityExport(array $rows, string $name = 'lead-routing-form-v2-va-2026-09-17'): string
{
    $headers = [
        'Business Email', 'Name (First)', 'Name (Last)', 'Phone', 'Phone (Region Code)',
        "What is your company's current monthly revenue?", 'timezone', 'timestamp',
        'Consent (Consent)', 'Consent (Text)',
        'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Term', 'UTM Content',
        'gclid', 'fbclid', 'utm_id', 'oppref', 'partner',
        'handl_landing_page (HandL)', 'handl_landing_page_base (HandL)',
        'handl_original_ref (HandL)', 'wbraid (HandL)',
        'intake_form', 'data_source', 'referrer_rewardful_id', 'submission_type',
        'Scheduler link', 'Entry Id', 'Entry Date', 'Date Updated', 'Source Url',
        'User Agent', 'User IP', 'Business Email (Zerobounce Result)',
    ];

    $path = sys_get_temp_dir().'/'.Str::random(8).'-'.$name.'.csv';
    $handle = fopen($path, 'w');

    // The BOM lands before the first field's opening quote, exactly as Gravity writes it.
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $headers, ',', '"', '');

    foreach ($rows as $row) {
        fputcsv($handle, array_map(
            static fn (string $header): string => $row[$header] ?? '',
            $headers,
        ), ',', '"', '');
    }

    fclose($handle);

    return $path;
}

/**
 * One entry, with the fields a real row always carries already filled in.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function gravityRow(array $overrides = []): array
{
    return array_merge([
        'Business Email' => 'lead@example.com',
        'Name (First)' => 'Dana',
        'Name (Last)' => 'Okafor',
        'Phone' => '+13055550199',
        'Phone (Region Code)' => 'US',
        "What is your company's current monthly revenue?" => '$10k to $50k Per Month',
        'timezone' => 'America/New_York',
        'Consent (Consent)' => 'Checked',
        'Consent (Text)' => "I agree to receive SMS reminders.\nStandard rates apply.",
        'intake_form' => 'yes',
        'data_source' => 'Salvatori Forms',
        'referrer_rewardful_id' => 'No Rewardful ID set',
        'submission_type' => 'Partial',
        'Entry Id' => '50001',
        'Entry Date' => '2026-09-10 00:15:00',
        'Date Updated' => '2026-09-10 14:15:00',
        'Source Url' => 'https://remoteleverage.com/wp-json/rl/v1/calendly/validate-form',
        'User IP' => '203.0.113.7',
        'Business Email (Zerobounce Result)' => 'valid',
    ], $overrides);
}

function gravityImporter(): GravityLeadImporter
{
    return new GravityLeadImporter(new AttributionEngine, app(IdentityResolver::class));
}

/**
 * @param  array<int, array<string, string>>  $rows
 * @return array<int, array<string, mixed>>
 */
function gravityEntries(array $rows): array
{
    $reader = new GravityCsvReader(gravityExport($rows));
    $mapper = new GravityLeadMapper;
    $entries = [];

    foreach ($reader->rows() as $row) {
        $entry = $mapper->map($row, $reader->formSlug());

        if ($entry !== null) {
            $entries[] = $entry;
        }
    }

    return $entries;
}

beforeEach(function () {
    Lead::truncate();
});

describe('GravityCsvReader', function () {
    test('the BOM does not swallow the first column', function () {
        /*
         * The failure this guards is total and silent: `fgetcsv` sees a first field that does not
         * start with a quote, hands back `"Business Email"` with the quotes attached, every
         * lookup of that header misses, and the whole import writes leads with no email.
         */
        $reader = new GravityCsvReader(gravityExport([gravityRow()]));

        expect($reader->headers()[0])->toBe('Business Email');

        $rows = iterator_to_array($reader->rows());

        expect($rows[0]['Business Email'])->toBe('lead@example.com');
    });

    test('HTML entities and embedded newlines survive the round trip intact', function () {
        $reader = new GravityCsvReader(gravityExport([gravityRow([
            'handl_landing_page (HandL)' => 'https://remoteleverage.com/hire-va/?utm_source=bing&amp;utm_medium=ppc',
            'wbraid (HandL)' => 'Cj0KCQ\\ABCD',
        ])]));

        $rows = iterator_to_array($reader->rows());

        // A landing URL stored with `&amp;` does not resolve, and reading `utm_medium` back out
        // of it yields `amp;utm_medium`.
        expect($rows[0]['handl_landing_page (HandL)'])
            ->toBe('https://remoteleverage.com/hire-va/?utm_source=bing&utm_medium=ppc')
            // A backslash under PHP's default escape character eats the following delimiter and
            // shifts every remaining column one place to the left.
            ->and($rows[0]['wbraid (HandL)'])->toBe('Cj0KCQ\\ABCD')
            ->and($rows[0]['Consent (Text)'])->toContain("\n");
    });

    test('a row whose width does not match the header is counted, never guessed at', function () {
        $path = gravityExport([gravityRow()]);
        file_put_contents($path, '"short","row"'.PHP_EOL, FILE_APPEND);

        $reader = new GravityCsvReader($path);
        $rows = iterator_to_array($reader->rows());

        expect($rows)->toHaveCount(1)
            ->and($reader->malformedCount())->toBe(1);
    });
});

describe('GravityLeadMapper', function () {
    test('the submission timestamp comes from Date Updated, not Entry Date', function () {
        /*
         * The two columns differ by a fixed offset — 7h for a Final, 14h for a Partial, because
         * `Entry Date` renders in the site's local zone and partials created through the REST
         * endpoint are shifted twice. Only `Date Updated` is UTC. Reading the wrong one does not
         * fail; it files every lead in the wrong hour.
         */
        $entries = gravityEntries([gravityRow([
            'Entry Date' => '2026-09-10 00:15:00',
            'Date Updated' => '2026-09-10 14:15:00',
        ])]);

        expect($entries[0]['submitted_at']->toDateTimeString())->toBe('2026-09-10 14:15:00')
            // Still recorded, because it is the value the Gravity admin displays and searches on.
            ->and($entries[0]['attribution']['gravity']['entry_date_local'])->toBe('2026-09-10 00:15:00');
    });

    test('Final means booked and Partial means abandoned, regardless of the scheduler link', function () {
        // 1,058 of 2,775 real Finals carry no scheduler link. Treating the link as the signal
        // would discard better than a third of the conversions.
        $entries = gravityEntries([
            gravityRow(['Entry Id' => '1', 'submission_type' => 'Final', 'Scheduler link' => '']),
            gravityRow(['Entry Id' => '2', 'submission_type' => 'Partial', 'Scheduler link' => '']),
        ]);

        expect($entries[0]['booked'])->toBeTrue()
            ->and($entries[1]['booked'])->toBeFalse();
    });

    test('the landing URL comes from HandL rather than the REST endpoint in Source Url', function () {
        $entries = gravityEntries([gravityRow([
            'handl_landing_page (HandL)' => 'https://remoteleverage.com/hire-va/?utm_source=bing',
            'handl_landing_page_base (HandL)' => 'https://remoteleverage.com/hire-va/',
            'handl_original_ref (HandL)' => 'https://www.bing.com/',
        ])]);

        expect($entries[0]['columns']['landing_url'])->toBe('https://remoteleverage.com/hire-va/?utm_source=bing')
            ->and($entries[0]['columns']['landing_page_base'])->toBe('https://remoteleverage.com/hire-va/')
            ->and($entries[0]['columns']['referrer_url'])->toBe('https://www.bing.com/')
            // Every Partial posts from the validate-form endpoint, a URL no visitor ever saw.
            ->and($entries[0]['columns'])->not->toHaveKey('source_url');
    });

    test("Rewardful's no-id sentinel is not stored as an id", function () {
        // The field is never blank; it holds the literal string "No Rewardful ID set", which
        // stored unchecked gives every organic lead a referral id that is really an error message.
        $entries = gravityEntries([gravityRow(['referrer_rewardful_id' => 'No Rewardful ID set'])]);

        expect($entries[0]['attribution']['gravity'])->not->toHaveKey('rewardful_id');
    });

    test('a header the mapper has never seen is reported rather than dropped in silence', function () {
        $path = gravityExport([gravityRow()]);
        $contents = file_get_contents($path);
        file_put_contents($path, str_replace('"Business Email"', '"Business Email","Newly Added Field"', $contents, $count));

        $unmapped = (new GravityLeadMapper)->unmappedHeaders((new GravityCsvReader($path))->headers());

        expect($unmapped)->toContain('Newly Added Field');
    });

    test('every header in a real export is accounted for', function () {
        // The guard against the reverse mistake: a column silently going nowhere because nobody
        // added it to a list. Uses the full header set the production form exports.
        $unmapped = (new GravityLeadMapper)->unmappedHeaders((new GravityCsvReader(gravityExport([gravityRow()])))->headers());

        expect($unmapped)->toBe([]);
    });
});

describe('GravityLeadImporter', function () {
    test('a Partial and the Final that followed it are one person, not two leads', function () {
        /*
         * Gravity writes a Partial when the form validates and a Final when the visitor books.
         * Those are one person converting. Folding per entry would double every conversion in
         * the dashboard — 7,397 entries are 3,969 people in the real backfill.
         */
        $leads = gravityImporter()->fold(gravityEntries([
            gravityRow(['Entry Id' => '1', 'submission_type' => 'Partial', 'Date Updated' => '2026-09-10 14:15:00']),
            gravityRow(['Entry Id' => '2', 'submission_type' => 'Final', 'Date Updated' => '2026-09-10 15:02:00']),
        ]));

        expect($leads)->toHaveCount(1);

        $lead = $leads['lead@example.com'];

        expect($lead['status'])->toBe('booked')
            ->and($lead['created_at'])->toBe('2026-09-10 14:15:00')
            ->and($lead['updated_at'])->toBe('2026-09-10 15:02:00')
            ->and($lead['attribution']['gravity']['entries'])->toBe(2)
            ->and($lead['attribution']['gravity']['finals'])->toBe(1)
            ->and($lead['attribution']['gravity']['entry_ids'])->toBe([1, 2]);
    });

    test('columns take the last non-empty value and attribution takes the first', function () {
        // The precedence CaptureLeadAction applies across repeat submissions, so that an imported
        // row and a captured row mean the same thing in the same dashboard.
        $leads = gravityImporter()->fold(gravityEntries([
            gravityRow([
                'Entry Id' => '1',
                'Date Updated' => '2026-09-10 14:15:00',
                'Name (Last)' => 'Okafor',
                'UTM Source' => 'facebook',
                'wbraid (HandL)' => 'first-touch',
            ]),
            gravityRow([
                'Entry Id' => '2',
                'Date Updated' => '2026-09-11 09:00:00',
                'Name (Last)' => 'Okafor-Reyes',
                // Blank on the later submission: it must not erase what the first one carried.
                'UTM Source' => '',
                'wbraid (HandL)' => 'second-touch',
            ]),
        ]));

        $lead = $leads['lead@example.com'];

        expect($lead['last_name'])->toBe('Okafor-Reyes')
            ->and($lead['utm_source'])->toBe('facebook')
            // The acquisition value is the one worth keeping.
            ->and($lead['attribution']['handl']['wbraid'])->toBe('first-touch');
    });

    test('consent is stamped from the first submission that gave it and never cleared', function () {
        $leads = gravityImporter()->fold(gravityEntries([
            gravityRow(['Entry Id' => '1', 'Date Updated' => '2026-09-10 14:15:00', 'Consent (Consent)' => 'Not Checked']),
            gravityRow(['Entry Id' => '2', 'Date Updated' => '2026-09-11 09:00:00', 'Consent (Consent)' => 'Checked']),
            gravityRow(['Entry Id' => '3', 'Date Updated' => '2026-09-12 09:00:00', 'Consent (Consent)' => 'Not Checked']),
        ]));

        expect($leads['lead@example.com']['consent_at'])->toBe('2026-09-11 09:00:00');
    });

    test('leads with different field sets do not misalign on a bulk insert', function () {
        /*
         * A multi-row `insert()` takes its column list from the first row and pairs every later
         * row against it positionally. Rows with differing key sets do not error — they write one
         * lead's values into another lead's columns, which is the single worst outcome available
         * to an importer because the result still looks like data.
         */
        $importer = gravityImporter();

        $importer->persist($importer->fold(gravityEntries([
            gravityRow([
                'Business Email' => 'rich@example.com',
                'Entry Id' => '1',
                'gclid' => 'gclid-value',
                'Scheduler link' => 'https://remoteleverage.com/scheduler-link?id=abc',
                'handl_landing_page (HandL)' => 'https://remoteleverage.com/hire-va/',
            ]),
            // Carries none of the three fields above.
            gravityRow([
                'Business Email' => 'sparse@example.com',
                'Entry Id' => '2',
                'Name (First)' => 'Sparse',
                'Name (Last)' => 'Record',
            ]),
        ])));

        $sparse = Lead::query()->where('email', 'sparse@example.com')->sole();
        $rich = Lead::query()->where('email', 'rich@example.com')->sole();

        expect($sparse->name)->toBe('Sparse Record')
            ->and($sparse->gclid)->toBeNull()
            ->and($sparse->scheduler_link)->toBeNull()
            ->and($sparse->landing_url)->toBeNull()
            ->and($rich->gclid)->toBe('gclid-value')
            ->and($rich->scheduler_link)->toBe('https://remoteleverage.com/scheduler-link?id=abc');
    });

    test('a lead captured by the live form is never overwritten by a backfill', function () {
        /*
         * The re-run guard. A real capture is the only record of what the customer actually did;
         * a backfill that rewrites one is worse than a backfill that skips it. Imported rows are
         * told apart by `attribution->gravity`, which the live path never writes.
         */
        $live = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Live Capture',
            'email' => 'lead@example.com',
            'status' => 'booking_pending',
            'source_type' => 'organic',
            'attribution' => ['handl' => ['traffic_source' => 'Direct']],
        ]);

        $importer = gravityImporter();
        $result = $importer->persist($importer->fold(gravityEntries([gravityRow()])));

        $live->refresh();

        expect($result['skipped_live'])->toBe(1)
            ->and($result['created'])->toBe(0)
            ->and($live->name)->toBe('Live Capture')
            ->and($live->status)->toBe('booking_pending');
    });

    test('re-importing the same export changes nothing', function () {
        $importer = gravityImporter();
        $rows = [gravityRow(['Entry Id' => '1']), gravityRow(['Business Email' => 'other@example.com', 'Entry Id' => '2'])];

        $importer->persist($importer->fold(gravityEntries($rows)));

        $before = Lead::query()->orderBy('email')->get(['email', 'status', 'created_at', 'updated_at'])->toJson();

        $second = $importer->persist($importer->fold(gravityEntries($rows)));

        expect(Lead::query()->count())->toBe(2)
            ->and($second['created'])->toBe(0)
            ->and($second['updated'])->toBe(2)
            ->and(Lead::query()->orderBy('email')->get(['email', 'status', 'created_at', 'updated_at'])->toJson())->toBe($before);
    });

    test('identity resolution does not restamp the timestamps the fold computed', function () {
        /*
         * `IdentityResolver` writes `profile_id` back with `saveQuietly()`, which still touches
         * `updated_at`, and its merge path re-points leads with a builder `update()` that stamps
         * it too. Left alone, every backfilled lead's `updated_at` becomes the moment of the
         * import, so "leads updated this week" answers a question about the import job.
         */
        $importer = gravityImporter();

        $leads = $importer->fold(gravityEntries([
            gravityRow(['Entry Id' => '1', 'Date Updated' => '2026-09-10 14:15:00']),
            // Same phone, different email: two leads that the identity graph merges into one
            // profile, which is the path that stamps `updated_at` over the top.
            gravityRow([
                'Business Email' => 'second@example.com',
                'Entry Id' => '2',
                'Date Updated' => '2026-09-11 09:00:00',
            ]),
        ]));

        $importer->persist($leads);
        $importer->resolveIdentities($leads);

        $first = Lead::query()->where('email', 'lead@example.com')->sole();
        $second = Lead::query()->where('email', 'second@example.com')->sole();

        expect($first->updated_at->toDateTimeString())->toBe('2026-09-10 14:15:00')
            ->and($second->updated_at->toDateTimeString())->toBe('2026-09-11 09:00:00')
            ->and($first->profile_id)->not->toBeNull()
            // Both numbers are the same person's, so the graph should have linked them.
            ->and($first->profile_id)->toBe($second->profile_id);
    });
});
