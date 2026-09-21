<?php

declare(strict_types=1);

use App\Domains\Marketing\Data\Finding;
use App\Domains\Marketing\Services\FindingDismissals;
use Carbon\CarbonImmutable;

/**
 * Dismissing a finding so it stops being announced.
 *
 * The phantom-booking finding forced this. It names somebody whose meeting does not exist, the
 * fix is to ring them, and ringing them changes nothing about the row it is read from — so it
 * would repeat on every hourly card until midnight, long after it was dealt with. A warning that
 * cannot be cleared gets cleared anyway, by the reader, who stops reading the warnings.
 *
 * The safety model is that the scope belongs to the finding and not to whoever clicks the button.
 */
beforeEach(function () {
    update_option(FindingDismissals::OPTION, []);
    config(['marketing.cost_alert.timezone' => 'America/New_York']);
});

$monday = CarbonImmutable::parse('2026-09-21 13:36:00', 'America/New_York');

test('a live finding is announced until somebody dismisses it', function () use ($monday) {
    $marvin = Finding::settled('booking-no-meeting:4343', 'Marvin Rodriguez is marked booked…');
    $dismissals = new FindingDismissals;

    expect($dismissals->partition([$marvin], $monday)['live'])->toHaveCount(1);

    $dismissals->dismiss($marvin, $monday);
    $after = $dismissals->partition([$marvin], $monday);

    expect($after['live'])->toBe([])
        ->and($after['dismissed'])->toHaveCount(1)
        ->and($after['dismissed'][0]['key'])->toBe('booking-no-meeting:4343');
});

test('a finding about a person stays dismissed tomorrow, because ringing them settled it', function () use ($monday) {
    $marvin = Finding::settled('booking-no-meeting:4343', 'Marvin Rodriguez is marked booked…');

    $dismissals = new FindingDismissals;
    $dismissals->dismiss($marvin, $monday);

    expect($dismissals->partition([$marvin], $monday->addDays(30))['live'])->toBe([]);
});

test('a finding about a day comes back tomorrow, so a recurrence is never swallowed', function () use ($monday) {
    $warehouse = Finding::daily('warehouse-ahead-of-site', 'The warehouse reports 40 bookings…');

    $dismissals = new FindingDismissals;
    $dismissals->dismiss($warehouse, $monday);

    // Quiet for the rest of today…
    expect($dismissals->partition([$warehouse], $monday->addHours(4))['live'])->toBe([]);

    // …and announced again the next time it happens, which is the whole point.
    expect($dismissals->partition([$warehouse], $monday->addDay())['live'])->toHaveCount(1);
});

test('an expired dismissal is dropped from storage rather than accumulating', function () use ($monday) {
    $warehouse = Finding::daily('warehouse-ahead-of-site', 'The warehouse reports 40 bookings…');

    $dismissals = new FindingDismissals;
    $dismissals->dismiss($warehouse, $monday);

    expect($dismissals->all())->toHaveCount(1);

    $dismissals->partition([$warehouse], $monday->addDay());

    // Otherwise the option grows by a row per condition per day, forever.
    expect($dismissals->all())->toBe([]);
});

test('the finding owns the scope, not the stored row', function () use ($monday) {
    $dismissals = new FindingDismissals;

    // Dismissed while it was permanent…
    $dismissals->dismiss(new Finding('warehouse-ahead-of-site', 'x', permanent: true), $monday);

    // …and re-read once the code says it is a daily one. The live definition wins, so a scope
    // that was wrong when it was stored cannot silence a condition for good.
    $daily = Finding::daily('warehouse-ahead-of-site', 'x');

    expect($dismissals->partition([$daily], $monday->addDay())['live'])->toHaveCount(1);
});

test('undo puts it back', function () use ($monday) {
    $marvin = Finding::settled('booking-no-meeting:4343', 'Marvin Rodriguez is marked booked…');

    $dismissals = new FindingDismissals;
    $dismissals->dismiss($marvin, $monday);
    $dismissals->restore($marvin->key);

    expect($dismissals->partition([$marvin], $monday)['live'])->toHaveCount(1);
});

test('dismissing one person does not silence another', function () use ($monday) {
    $marvin = Finding::settled('booking-no-meeting:4343', 'Marvin Rodriguez…');
    $susan = Finding::settled('booking-no-meeting:4302', 'Susan Ornstein…');

    $dismissals = new FindingDismissals;
    $dismissals->dismiss($marvin, $monday);

    $partitioned = $dismissals->partition([$marvin, $susan], $monday);

    // The reason these are one finding each rather than one sentence listing everybody.
    expect($partitioned['live'])->toHaveCount(1)
        ->and($partitioned['live'][0]->key)->toBe('booking-no-meeting:4302');
});

test('a dismissal records who cleared it and when', function () use ($monday) {
    $dismissals = new FindingDismissals;
    $dismissals->dismiss(Finding::settled('booking-no-meeting:4343', 'Marvin…'), $monday);

    $row = $dismissals->all()['booking-no-meeting:4343'];

    // The dashboard shows this next to the greyed line; without it a cleared warning is a warning
    // that vanished and nobody can say who cleared it.
    expect($row['at'])->toStartWith('2026-09-21')
        ->and($row)->toHaveKey('by');
});
