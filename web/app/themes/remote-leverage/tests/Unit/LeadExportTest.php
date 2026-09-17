<?php

declare(strict_types=1);

use App\Domains\Lead\Export\LeadExportColumns;
use App\Domains\Lead\Export\LeadExportJobStore;
use App\Domains\Lead\Export\LeadExportOptions;
use App\Domains\Lead\Export\LeadExportRunner;
use App\Domains\Lead\Models\Lead;

/*
 * The batched CSV export.
 *
 * The behaviour worth pinning here is what a single-request exporter never had to get right:
 * a job that stops after every few seconds and has to resume exactly where it left off. A
 * cursor that drifts by one row does not fail — it writes a file with a lead missing, or a lead
 * twice, and nothing downstream can tell.
 */

function exportLead(array $attributes = []): Lead
{
    static $sequence = 0;
    $sequence++;

    return Lead::create(array_merge([
        'uuid' => 'uuid-'.$sequence,
        'name' => 'Lead '.$sequence,
        'email' => 'lead'.$sequence.'@example.com',
        'phone' => '+1305555'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
        'phone_country' => 'US',
        'status' => 'captured',
        'source_type' => 'organic',
    ], $attributes));
}

/** Run a job to completion the way the browser does, and hand back the rows written. */
function runExport(LeadExportOptions $options): array
{
    $runner = app(LeadExportRunner::class);
    $job = $runner->start($options);

    $guard = 0;
    while (! $job->isFinished() && $guard++ < 100) {
        $job = $runner->step($job);
    }

    expect($job->phase)->toBe('done');

    // Same escape the writer uses; PHP 8.4 deprecates leaving it to the default.
    $rows = array_map(
        static fn (string $line) => str_getcsv($line, ',', '"', ''),
        array_filter(explode("\n", (string) file_get_contents($job->path))),
    );

    return ['job' => $job, 'rows' => $rows];
}

describe('LeadExportOptions', function () {
    test('drops a column group it does not recognise', function () {
        $options = LeadExportOptions::fromRequest(['groups' => 'contact,not_a_group,tracking']);

        expect($options->groups)->toBe(['contact', 'tracking']);
    });

    test('identity columns are written whether or not they were asked for', function () {
        // A row with no id, name or email is not a lead export.
        expect(LeadExportOptions::fromRequest(['groups' => 'contact'])->effectiveGroups())
            ->toContain('identity');
    });

    test('a date that is not a date is ignored rather than passed to the query', function () {
        $options = LeadExportOptions::fromRequest(['from' => 'yesterday', 'to' => '2026-09-01']);

        expect($options->from)->toBe('')
            ->and($options->to)->toBe('2026-09-01');
    });

    test('an unknown status is dropped instead of returning nothing', function () {
        // A typo'd status that reached the query would silently export an empty file.
        expect(LeadExportOptions::fromRequest(['status' => "'; DROP"])->status)->toBe('');
    });

    test('survives a round trip through the job store format', function () {
        $options = LeadExportOptions::fromRequest([
            'groups' => 'contact,referral',
            'status' => 'booked',
            'from' => '2026-01-01',
            'include_deleted' => '1',
            'limit' => 500,
            'search' => 'acme',
        ]);

        expect(LeadExportOptions::fromArray($options->toArray()))->toEqual($options);
    });
});

describe('LeadExportColumns', function () {
    test('headers and row values stay in step', function () {
        // The previous exporter listed headers and values in two separate places; one added
        // without the other shifts every value in the file one column to the left.
        $groups = ['identity', 'contact', 'attribution'];
        $lead = exportLead(['company' => 'Acme']);

        expect(LeadExportColumns::row($lead, $groups))
            ->toHaveCount(count(LeadExportColumns::headers($groups)));
    });

    test('an unselected group contributes no columns', function () {
        $without = LeadExportColumns::headers(['identity']);
        $with = LeadExportColumns::headers(['identity', 'click_ids']);

        expect($with)->toContain('GCLID')
            ->and($without)->not->toContain('GCLID');
    });
});

describe('LeadExportRunner', function () {
    beforeEach(function () {
        Lead::withTrashed()->forceDelete();
    });

    test('writes every matching lead exactly once', function () {
        foreach (range(1, 12) as $ignored) {
            exportLead();
        }

        ['rows' => $rows] = runExport(LeadExportOptions::fromRequest([]));

        $ids = array_map(static fn ($row) => $row[0], array_slice($rows, 1));

        expect($rows)->toHaveCount(13)                 // 12 leads plus the header
            ->and($ids)->toHaveCount(count(array_unique($ids)));
    });

    test('resumes from the cursor rather than an offset', function () {
        /*
         * The reason the cursor is an id and not an offset: a lead captured between two batches
         * shifts every later offset by one and drops a row from the file. Here the new lead has
         * the highest id, so an offset walk would re-read the row it had already written.
         */
        foreach (range(1, 6) as $ignored) {
            exportLead();
        }

        $runner = app(LeadExportRunner::class);
        $job = $runner->start(LeadExportOptions::fromRequest([]));

        exportLead(['name' => 'Captured mid-export']);

        while (! $job->isFinished()) {
            $job = $runner->step($job);
        }

        $rows = array_filter(explode("\n", (string) file_get_contents($job->path)));

        expect($job->written)->toBe(6)
            ->and($rows)->toHaveCount(7)
            ->and(implode('', $rows))->not->toContain('Captured mid-export');
    });

    test('the limit takes the newest leads', function () {
        $oldest = exportLead(['name' => 'Oldest']);
        foreach (range(1, 5) as $ignored) {
            exportLead();
        }
        $newest = exportLead(['name' => 'Newest']);

        ['rows' => $rows] = runExport(LeadExportOptions::fromRequest(['limit' => 3]));
        $body = implode('', array_map(static fn ($r) => implode(',', $r), array_slice($rows, 1)));

        expect($rows)->toHaveCount(4)
            ->and($body)->toContain('Newest')
            ->and($body)->not->toContain('Oldest')
            ->and($oldest->id)->toBeLessThan($newest->id);
    });

    test('honours the status filter', function () {
        exportLead(['status' => 'booked', 'name' => 'Booked one']);
        exportLead(['status' => 'partial', 'name' => 'Partial one']);

        ['rows' => $rows] = runExport(LeadExportOptions::fromRequest(['status' => 'booked']));

        expect($rows)->toHaveCount(2)
            ->and(implode('', $rows[1]))->toContain('Booked one');
    });

    test('soft-deleted leads are left out unless asked for', function () {
        exportLead(['name' => 'Alive']);
        exportLead(['name' => 'Gone'])->delete();

        ['rows' => $without] = runExport(LeadExportOptions::fromRequest([]));
        ['rows' => $with] = runExport(LeadExportOptions::fromRequest(['include_deleted' => '1']));

        expect($without)->toHaveCount(2)
            ->and($with)->toHaveCount(3);
    });

    test('a filter that matches nothing still produces a readable file', function () {
        exportLead();

        ['job' => $job, 'rows' => $rows] = runExport(LeadExportOptions::fromRequest(['status' => 'canceled']));

        expect($rows)->toHaveCount(1)              // headers only
            ->and($job->percent())->toBe(100)
            ->and($job->label())->toContain('headers only');
    });

    test('the file opens as UTF-8 in Excel', function () {
        // Without the BOM every accented name in the export comes out mojibaked.
        exportLead(['name' => 'Adrián Salvatori']);

        ['job' => $job] = runExport(LeadExportOptions::fromRequest([]));

        expect(substr((string) file_get_contents($job->path), 0, 3))->toBe("\xEF\xBB\xBF");
    });

    test('progress reaches a hundred percent when it is done', function () {
        foreach (range(1, 4) as $ignored) {
            exportLead();
        }

        ['job' => $job] = runExport(LeadExportOptions::fromRequest([]));

        expect($job->percent())->toBe(100)
            ->and($job->written)->toBe(4)
            ->and($job->label())->toContain('4 leads exported');
    });
});

describe('LeadExportJobStore', function () {
    test('keeps only the most recent exports, files included', function () {
        // Each file is the lead table in plain text; they do not get to pile up.
        $store = app(LeadExportJobStore::class);
        $paths = [];

        foreach (range(1, LeadExportJobStore::RETENTION + 2) as $ignored) {
            $job = $store->create(LeadExportOptions::fromRequest([]));
            file_put_contents($job->path, 'x');
            $paths[] = $job->path;
        }

        expect($store->ids())->toHaveCount(LeadExportJobStore::RETENTION)
            ->and(is_file($paths[0]))->toBeFalse()
            ->and(is_file($paths[count($paths) - 1]))->toBeTrue();
    });

    test('refuses a job id that is not one it minted', function () {
        // The id is concatenated into an option name, so it never reaches that unvalidated.
        expect(app(LeadExportJobStore::class)->find('../../wp-config'))->toBeNull();
    });
});
