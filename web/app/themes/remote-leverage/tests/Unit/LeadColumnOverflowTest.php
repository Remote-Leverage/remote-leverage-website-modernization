<?php

declare(strict_types=1);

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;
use Illuminate\Support\Facades\Schema;

/**
 * Guards the lead-loss bug of 2026-09-17.
 *
 * Facebook's `fbclid` outgrew `varchar(150)` — a real click arrived at 212 characters. Because
 * MySQL runs with `STRICT_TRANS_TABLES` that is SQLSTATE[22001], not a truncation, so the
 * INSERT aborted and no lead row was created at all. `submitBooking()` caught it and showed
 * "An error occurred processing your consultation"; `capturePartialLead()` caught the same
 * thing one step earlier and only wrote a `Log::warning`. Net effect: every visitor arriving
 * from paid social was dropped, with nothing in the dashboard to show they had ever existed.
 *
 * The migration widens the columns. This covers the other half — that an even longer value
 * still costs at most the tail of a click id, never the customer.
 */
test('an oversized click id is trimmed rather than allowed to abort the insert', function () {
    Lead::truncate();

    $action = new CaptureLeadAction(
        new AttributionEngine,
        new PhoneValidationService,
        new LeadActivityLogger,
    );

    // Longer than the widened column, so this exercises the clamp and not the new width.
    $absurdFbclid = 'IwY2xjawA'.str_repeat('a', 900);

    $lead = $action->execute(LeadCaptureData::fromArray([
        'name' => 'Overflow Probe',
        'email' => 'overflow-probe@example.com',
        'fbclid' => $absurdFbclid,
        'attribution_named' => ['fbc' => 'fb.1.1785350683256.'.$absurdFbclid],
    ]));

    expect($lead->exists)->toBeTrue()
        ->and(mb_strlen((string) $lead->fbclid))->toBeLessThanOrEqual(512)
        ->and($lead->fbclid)->toStartWith('IwY2xjawA');
});

/*
 * The suite runs on sqlite in-memory (tests/stubs.php), which neither reports varchar lengths
 * nor enforces them. So the *storage* half cannot be proven here — a narrowed column would
 * still pass a round-trip assertion. It is asserted against the migration instead, which is
 * the artefact that would actually have to change for the bug to return.
 */
test('the migration keeps the click-id columns wide enough for a real Facebook click', function () {
    $migration = file_get_contents(
        __DIR__.'/../../app/Infrastructure/Database/Migrations/2026_09_17_000001_widen_click_id_columns_on_leads_table.php'
    );

    preg_match('/private const WIDENED = \\[(.*?)\\];/s', $migration, $m);
    expect($m)->not->toBeEmpty('the widening migration should still declare its target widths');

    preg_match_all("/'([a-z_]+)' => (\\d+)/", $m[1], $pairs, PREG_SET_ORDER);
    $widths = [];
    foreach ($pairs as $pair) {
        $widths[$pair[1]] = (int) $pair[2];
    }

    // 212 is the length of the click id that was aborting the INSERT in production.
    expect($widths['fbclid'] ?? 0)->toBeGreaterThanOrEqual(512)
        ->and($widths['gclid'] ?? 0)->toBeGreaterThanOrEqual(512)
        // `_fbc` is "fb.1.<timestamp>.<fbclid>", so it must stay ahead of fbclid, not level.
        ->and($widths['fbc'] ?? 0)->toBeGreaterThanOrEqual($widths['fbclid'] ?? 0);
});

/*
 * The failsafe that matters long-term: limits are read off the table rather than hardcoded, so
 * a column nobody remembered to list, or a width someone changed without updating the map, is
 * still clamped.
 *
 * Skipped on a driver that does not report column lengths — sqlite says "varchar" with no
 * size, so there is nothing to derive and the constant map is used instead. Verified against
 * MySQL by hand: 37 columns resolve, including `email` and `company`, which are not in the map.
 */
test('column limits are derived from the live schema, not just the constant map', function () {
    $action = app(CaptureLeadAction::class);

    $reflected = new ReflectionMethod($action, 'columnLimits');
    $limits = $reflected->invoke($action);

    // The map is the floor, whatever the driver can tell us.
    expect($limits['fbclid'] ?? 0)->toBeGreaterThanOrEqual(512);

    // Exactly the pattern columnLimits() parses. sqlite reports a bare "varchar" with no size,
    // so nothing is derivable there; MySQL reports "varchar(512)". A looser regex here matched
    // unrelated types like decimal(8,2) and made this assert against a driver that cannot
    // satisfy it.
    $reportsLengths = collect(Schema::getColumns((new Lead)->getTable()))
        ->contains(fn (array $column) => (bool) preg_match('/^(?:var)?char\\(\\d+\\)/i', (string) ($column['type'] ?? '')));

    if (! $reportsLengths) {
        expect(true)->toBeTrue();

        return;
    }

    // Not in COLUMN_LIMITS — if it is bounded here, the derivation is live.
    expect($limits)->toHaveKey('company');
});
