<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use Carbon\Carbon;

/**
 * The picker labelled every slot in UTC.
 *
 * Calendly's `event_type_available_times` answers in UTC Zulu and ignores the `timezone`
 * parameter, and PHP discards the timezone argument to the DateTime constructor whenever the
 * string carries its own offset. So `Carbon::parse($slot->startTime, $this->timezone)` returned
 * the UTC clock time — rendered under a banner naming the visitor's zone, and on the
 * confirmation screen with that zone's name appended to it.
 *
 * On 2026-09-21 a lead picked the button reading "3:45pm", which was 15:45 UTC, and was booked
 * at 10:45 in their own Central time. Nothing moved the meeting: Calendly received the exact
 * instant behind the button. The button was mislabelled before it was ever clicked.
 */
const CALENDLY_SLOT_ISO = '2026-09-22T15:45:00Z';

/** The real shape, copied from a live `event_type_available_times` response. */
const CALENDLY_SLOT_ROW = [
    'invitees_remaining' => 1,
    'scheduling_url' => 'https://calendly.com/d/ctwz-ws4-6sg/va-hiring-consultation-t10-a/2026-09-22T15:45:00Z',
    'start_time' => CALENDLY_SLOT_ISO,
    'status' => 'available',
];

describe('slot labels', function () {
    test('a UTC slot is labelled in the visitor\'s zone, not in UTC', function () {
        $label = fn (string $tz) => Carbon::parse(CALENDLY_SLOT_ROW['start_time'])->setTimezone($tz)->format('g:ia');

        expect($label('America/Chicago'))->toBe('10:45am')
            ->and($label('America/New_York'))->toBe('11:45am')
            ->and($label('America/Los_Angeles'))->toBe('8:45am')
            ->and($label('UTC'))->toBe('3:45pm');
    });

    test('the argument form this replaced silently ignores the zone', function () {
        // Pins the language behaviour the bug rested on, so nobody reintroduces the shorter call.
        expect(Carbon::parse(CALENDLY_SLOT_ISO, 'America/Chicago')->format('g:ia'))->toBe('3:45pm')
            ->and(Carbon::parse(CALENDLY_SLOT_ISO)->setTimezone('America/Chicago')->format('g:ia'))->toBe('10:45am');
    });

    test('neither render site formats an absolute slot time with a parse-time zone', function () {
        $source = file_get_contents(__DIR__.'/../../app/Application/Livewire/Booking/MultistepBookingWizard.php');

        expect($source)
            ->toContain("Carbon::parse(\$slot->startTime)->setTimezone(\$this->timezone)->format('g:ia')")
            ->toContain('Carbon::parse($this->selectedSlot)->setTimezone($this->timezone)')
            ->not->toContain('Carbon::parse($slot->startTime, $this->timezone)')
            ->not->toContain('Carbon::parse($this->selectedSlot, $this->timezone)');
    });
});

describe('the visitor\'s own timezone', function () {
    test('the browser zone is detected on load and applied once', function () {
        $wizard = new class extends MultistepBookingWizard
        {
            public int $reloads = 0;

            public function loadMonthAvailability(): void
            {
                $this->reloads++;
            }

            public function loadSlotsForDate(string $date): void
            {
                $this->reloads++;
            }
        };

        $wizard->detectTimezone('Asia/Manila');

        expect($wizard->timezone)->toBe('Asia/Manila')
            ->and($wizard->reloads)->toBe(1);
    });

    test('detection never overrides a zone the visitor chose, and never trusts the client blindly', function () {
        $wizard = new class extends MultistepBookingWizard
        {
            public function loadMonthAvailability(): void {}

            public function loadSlotsForDate(string $date): void {}
        };

        $wizard->detectTimezone('Not/AZone');
        expect($wizard->timezone)->toBe('America/New_York');

        $wizard->updatedTimezone();
        $wizard->timezone = 'America/Chicago';
        $wizard->detectTimezone('Europe/London');

        expect($wizard->timezone)->toBe('America/Chicago');
    });

    test('a detected zone outside the offered list is still selectable', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->timezone = 'Asia/Manila';

        expect($wizard->timezoneChoices())->toContain('Asia/Manila')
            ->and($wizard->timezoneChoices()[0])->toBe('Asia/Manila')
            ->and($wizard->timezoneLabel('Asia/Manila'))->toBe('Asia, Manila')
            ->and($wizard->timezoneLabel('America/Chicago'))->toBe('Central Time (CT)');
    });

    test('both the desktop and mobile pickers offer the same zones', function () {
        $blade = file_get_contents(__DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php');

        expect(substr_count($blade, '$this->timezoneChoices()'))->toBe(2)
            ->and($blade)->toContain('$wire.detectTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone');
    });
});
