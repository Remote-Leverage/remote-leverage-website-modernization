<?php

declare(strict_types=1);

/**
 * The booking calendar's zone arithmetic, run under node against the real module.
 *
 * resources/js/booking-calendar.js is where every slot is labelled and every date is decided
 * since the calendar moved to the browser, so the guards that used to assert on PHP formatting
 * live here now — most of all the 2026-09-21 one, where a UTC clock time was printed under a
 * banner naming the visitor's zone.
 *
 * Run through Pest rather than a JS runner so it sits in the same `vendor/bin/pest` CI already
 * runs. The runners it needs node on have it preinstalled; locally, `npm run build` needs it too.
 */
function runBookingCalendar(string $script): array
{
    $module = realpath(__DIR__.'/../../resources/js/booking-calendar.js');
    $source = 'import * as cal from '.json_encode('file://'.$module).";\n"
        .'const out = (() => { '.$script." })();\n"
        .'process.stdout.write(JSON.stringify(out));';

    $process = proc_open(
        ['node', '--input-type=module', '-e', $source],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );

    expect($process)->not->toBeFalse('node could not be started; it is required for this test');

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    $status = proc_close($process);

    expect($status)->toBe(0, "node failed:\n".$stderr);

    return json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
}

describe('the browser calendar', function () {
    test('a UTC slot is labelled in the visitor\'s zone, not in UTC', function () {
        // The live Calendly slot behind the 2026-09-21 mislabel: offered as "3:45pm" to a
        // visitor in Central time, and booked at 10:45 in their own zone.
        $labels = runBookingCalendar(<<<'JS'
            const iso = '2026-09-22T15:45:00Z';
            return {
                chicago: cal.timeLabel(iso, 'America/Chicago'),
                newYork: cal.timeLabel(iso, 'America/New_York'),
                losAngeles: cal.timeLabel(iso, 'America/Los_Angeles'),
                utc: cal.timeLabel(iso, 'UTC'),
                midnight: cal.timeLabel('2026-09-25T04:00:00Z', 'America/New_York'),
            };
        JS);

        expect($labels)->toBe([
            'chicago' => '10:45am',
            'newYork' => '11:45am',
            'losAngeles' => '8:45am',
            'utc' => '3:45pm',
            'midnight' => '12:00am',
        ]);
    });

    test('a slot lands on the day the visitor\'s own clock reads', function () {
        $days = runBookingCalendar(<<<'JS'
            const now = Date.parse('2026-09-24T12:00:00Z');
            const slots = ['2026-09-25T15:00:00Z', '2026-09-25T02:00:00Z', '2026-09-24T10:00:00Z'];
            const group = (tz) => Object.fromEntries(cal.groupByDate(slots, tz, now));
            return {
                losAngeles: group('America/Los_Angeles'),
                manila: group('Asia/Manila'),
            };
        JS);

        // 02:00Z on the 25th is still the evening of the 24th in Los Angeles, and already the
        // morning of the 25th in Manila. The 10:00Z slot had started by `now`, so it is gone.
        expect($days['losAngeles'])->toBe([
            '2026-09-24' => ['2026-09-25T02:00:00Z'],
            '2026-09-25' => ['2026-09-25T15:00:00Z'],
        ])->and($days['manila'])->toBe([
            '2026-09-25' => ['2026-09-25T02:00:00Z', '2026-09-25T15:00:00Z'],
        ]);
    });

    test('the month grid matches what the server used to build', function () {
        $grid = runBookingCalendar(<<<'JS'
            const cells = cal.monthGrid(2026, 9, {
                todayKey: '2026-09-24',
                open: new Map([['2026-09-25', []], ['2026-09-22', []]]),
                selected: '2026-09-25',
            });
            const day = (d) => cells.find((c) => c.day === d);
            return {
                length: cells.length,
                blanks: cells.filter((c) => c.empty).length,
                past: day(23).isPast,
                today: day(24).isToday && ! day(24).isPast,
                open: day(25).hasAvailability && day(25).isSelected,
                // Open in the data but already gone by the visitor's clock.
                pastOpen: day(22).hasAvailability,
            };
        JS);

        // 1 September 2026 is a Tuesday: two leading blanks, then thirty days.
        expect($grid)->toBe([
            'length' => 32,
            'blanks' => 2,
            'past' => true,
            'today' => true,
            'open' => true,
            'pastOpen' => false,
        ]);
    });

    test('dates, months and the zone clock read the way the Blade copy did', function () {
        $text = runBookingCalendar(<<<'JS'
            return {
                long: cal.formatDay('2026-09-25', 'long'),
                weekday: cal.formatDay('2026-09-25', 'weekday'),
                monthDayYear: cal.formatDay('2026-09-25', 'monthDayYear'),
                weekdayMonthDay: cal.formatDay('2026-09-25', 'weekdayMonthDay'),
                monthDay: cal.formatDay('2026-09-25', 'monthDay'),
                month: cal.monthTitle(2026, 9),
                clock: cal.clockLabel('UTC', Date.parse('2026-09-24T00:05:00Z')),
            };
        JS);

        expect($text)->toBe([
            'long' => 'Friday, September 25, 2026',
            'weekday' => 'Friday',
            'monthDayYear' => 'September 25, 2026',
            'weekdayMonthDay' => 'Friday, September 25',
            'monthDay' => 'September 25',
            'month' => 'September 2026',
            'clock' => '00:05',
        ]);
    });

    test('a detected zone outside the offered list is still selectable', function () {
        $zones = runBookingCalendar(<<<'JS'
            const base = ['America/New_York', 'America/Chicago'];
            return {
                outside: cal.zoneChoices(base, 'Asia/Manila'),
                inside: cal.zoneChoices(base, 'America/Chicago'),
                fallback: cal.zoneLabel('Asia/Manila', { 'America/Chicago': 'Central Time (CT)' }),
                friendly: cal.zoneLabel('America/Chicago', { 'America/Chicago': 'Central Time (CT)' }),
            };
        JS);

        expect($zones)->toBe([
            'outside' => ['Asia/Manila', 'America/New_York', 'America/Chicago'],
            'inside' => ['America/New_York', 'America/Chicago'],
            'fallback' => 'Asia, Manila',
            'friendly' => 'Central Time (CT)',
        ]);
    });
});
