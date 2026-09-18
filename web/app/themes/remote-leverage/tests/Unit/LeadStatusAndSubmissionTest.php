<?php

declare(strict_types=1);

use App\Domains\Lead\Export\LeadExportOptions;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadStatus;
use App\Domains\Lead\Services\LeadSubmission;

/*
 * Statuses the column can hold, and the partial/final axis that is not a status at all.
 *
 * The bug these pin was silent by construction. "Partial Form Drops" counted `status = 'partial'`
 * — a value the status ENUM does not contain and nothing writes — so the card read 0 while 1,401
 * drop-offs sat in the table, and the matching toolbar option returned an empty list that looked
 * like an answer. Nothing errored, because nothing ever tried to write that value.
 *
 * The guard is therefore not "does the filter work" but "does every option correspond to
 * something the database can actually contain".
 */

function statusLead(array $attributes = []): Lead
{
    static $sequence = 0;
    $sequence++;

    return Lead::create(array_merge([
        'uuid' => 'status-'.$sequence,
        'name' => 'Status '.$sequence,
        'email' => 'status'.$sequence.'@example.com',
        'status' => 'captured',
        'source_type' => 'organic',
    ], $attributes));
}

beforeEach(function () {
    Lead::truncate();
});

/*
 * The list below is the `status` ENUM as the migration declares it. If a migration changes the
 * column, this test fails and LeadStatus has to be brought along — which is the whole point of
 * it existing, since a dropdown that drifts from the column fails silently at runtime.
 */
test('the status options are exactly the values the column accepts', function () {
    expect(LeadStatus::slugs())->toBe([
        'captured',
        'booking_pending',
        'booked',
        'booking_failed',
        'abandoned',
        'canceled',
    ]);
});

test('the statuses that used to be offered and cannot exist are rejected', function () {
    // Both were in the toolbar, the export and the manual status updater's allow-list.
    expect(LeadStatus::isKnown('partial'))->toBeFalse()
        ->and(LeadStatus::isKnown('qualified'))->toBeFalse();
});

test('every status has a readable label and a badge class that is not the bare fallback', function () {
    foreach (LeadStatus::slugs() as $slug) {
        expect(LeadStatus::label($slug))->not->toContain('_')
            // `rl-badge-booking_pending` is a class no stylesheet defines, so an unmapped
            // status would render as an unstyled word the moment it became selectable.
            ->and(LeadStatus::badgeClass($slug))->not->toBe('rl-badge');
    }

    expect(LeadStatus::label('booking_pending'))->toBe('Booking pending')
        ->and(LeadStatus::badgeClass('booking_pending'))->toBe('rl-badge-pending');
});

test('an unmapped status still renders legibly rather than blank', function () {
    expect(LeadStatus::label('some_future_state'))->toBe('Some future state')
        ->and(LeadStatus::badgeClass('some_future_state'))->toBe('rl-badge');
});

test('the partial filter counts submission_type, which is where the drop-off actually lives', function () {
    statusLead(['submission_type' => 'Partial', 'status' => 'abandoned']);
    statusLead(['submission_type' => 'Partial', 'status' => 'abandoned']);
    // A lead that abandoned once and came back: partial, and booked. Both filters must see it.
    statusLead(['submission_type' => 'Partial', 'status' => 'booked']);
    statusLead(['submission_type' => 'Final', 'status' => 'booked']);

    $partial = Lead::query();
    LeadSubmission::apply($partial, LeadSubmission::PARTIAL);

    $final = Lead::query();
    LeadSubmission::apply($final, LeadSubmission::FINAL);

    expect($partial->count())->toBe(3)
        ->and($final->count())->toBe(1)
        // Status and submission are independent axes, which is the reason both filters exist.
        ->and((clone $partial)->where('status', 'booked')->count())->toBe(1);
});

test('submission matching tolerates the casing and padding the column does not constrain', function () {
    // submission_type is free text, not an enum, so nothing stops a writer spelling it this way.
    statusLead(['submission_type' => 'partial']);
    statusLead(['submission_type' => ' Partial ']);
    statusLead(['submission_type' => 'PARTIAL']);

    $query = Lead::query();
    LeadSubmission::apply($query, LeadSubmission::PARTIAL);

    expect($query->count())->toBe(3);
});

test('an empty or unknown submission filter is a no-op rather than an empty list', function () {
    statusLead(['submission_type' => 'Partial']);
    statusLead(['submission_type' => 'Final']);

    foreach (['', 'nonsense', '   '] as $slug) {
        $query = Lead::query();
        LeadSubmission::apply($query, $slug);

        expect($query->count())->toBe(2);
    }
});

/*
 * Export parity, same promise as the other filters: a CSV started from a filtered list covers
 * what the list was showing.
 */
test('the export carries the submission filter and rejects a status that cannot exist', function () {
    statusLead(['submission_type' => 'Partial', 'status' => 'abandoned']);
    statusLead(['submission_type' => 'Final', 'status' => 'booked']);

    $options = LeadExportOptions::fromRequest(['submission' => 'partial']);

    expect($options->query()->count())->toBe(1);

    // 'partial' and 'qualified' were both accepted here and matched nothing in the column,
    // so either one silently exported an empty file.
    foreach (['partial', 'qualified', "booked'; DROP TABLE wp_rl_leads; --"] as $rejected) {
        expect(LeadExportOptions::fromRequest(['status' => $rejected])->status)->toBe('');
    }

    expect(LeadExportOptions::fromRequest(['status' => 'booking_failed'])->status)->toBe('booking_failed');
});

test('the submission filter survives the round trip through the job store', function () {
    $options = LeadExportOptions::fromRequest(['submission' => 'final', 'status' => 'booking_pending']);
    $resumed = LeadExportOptions::fromArray($options->toArray());

    expect($resumed->submission)->toBe('final')
        ->and($resumed->status)->toBe('booking_pending')
        ->and($resumed->describe())->toContain('Final (completed form)')
        ->and($resumed->describe())->toContain('status Booking pending');
});
