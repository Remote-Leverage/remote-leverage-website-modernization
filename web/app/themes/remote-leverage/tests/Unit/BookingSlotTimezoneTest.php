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

    test('the server no longer labels slots at all; the one time it formats is converted', function () {
        $source = file_get_contents(__DIR__.'/../../app/Application/Livewire/Booking/MultistepBookingWizard.php');

        // Slot labels are the browser's now (BookingCalendarJsTest). The confirmation line is
        // the only absolute time left on the server, and it must be converted, not re-parsed.
        expect($source)
            ->not->toContain("->format('g:ia')")
            ->toContain('Carbon::parse($this->selectedSlot)->setTimezone($this->timezone)')
            ->not->toContain('Carbon::parse($this->selectedSlot, $this->timezone)');
    });
});

describe('the visitor\'s own timezone', function () {
    test('detecting a zone only relabels: no availability is reloaded at any step', function () {
        $wizard = new class extends MultistepBookingWizard
        {
            public int $reloads = 0;

            public function loadAvailability(bool $fresh = false): void
            {
                $this->reloads++;
            }
        };

        $wizard->updatedBrowserTimezone('Asia/Manila');
        $wizard->currentStep = 3;
        $wizard->selectedDate = '2026-09-25';
        $wizard->timezoneChosen = false;
        $wizard->detectTimezone('Europe/London');

        expect($wizard->timezone)->toBe('Europe/London')
            ->and($wizard->reloads)->toBe(0);
    });

    test('the aliases browsers still report are translated, not dropped', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->detectTimezone('Asia/Calcutta');

        expect($wizard->timezone)->toBe('Asia/Kolkata')
            ->and(MultistepBookingWizard::canonicalTimezone('Europe/Kiev'))->toBe('Europe/Kyiv')
            ->and(MultistepBookingWizard::canonicalTimezone('Etc/UTC'))->toBe('UTC')
            ->and(MultistepBookingWizard::canonicalTimezone('Not/AZone'))->toBeNull();

        foreach (MultistepBookingWizard::TIMEZONE_ALIASES as $alias => $canonical) {
            expect(in_array($canonical, timezone_identifiers_list(), true))->toBeTrue("{$alias} maps to {$canonical}, which PHP does not know");
        }
    });

    test('the page load sends no request of its own to detect the zone', function () {
        $blade = file_get_contents(__DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php');

        expect($blade)->toContain("\$wire.\$set('browserTimezone', Intl.DateTimeFormat().resolvedOptions().timeZone || '', false)")
            ->and($blade)->not->toMatch('/x-init="[^"]*\$wire\.detectTimezone\(/');
    });

    test('detection never overrides a zone the visitor chose, and never trusts the client blindly', function () {
        $wizard = new MultistepBookingWizard;

        $wizard->detectTimezone('Not/AZone');
        expect($wizard->timezone)->toBe('America/New_York');

        $wizard->timezone = 'America/Chicago';
        $wizard->updatedTimezone();
        $wizard->detectTimezone('Europe/London');

        expect($wizard->timezone)->toBe('America/Chicago')
            ->and($wizard->timezoneChosen)->toBeTrue();
    });

    test('a zone picked in the browser is checked before it can reach Calendly', function () {
        $wizard = new MultistepBookingWizard;

        $wizard->timezone = 'Not/AZone';
        $wizard->updatedTimezone();

        expect($wizard->timezone)->toBe('America/New_York')
            ->and($wizard->timezoneChosen)->toBeFalse();

        $wizard->timezone = 'Asia/Saigon';
        $wizard->updatedTimezone();

        expect($wizard->timezone)->toBe('Asia/Ho_Chi_Minh')
            ->and($wizard->timezoneChosen)->toBeTrue();
    });

    test('both skins build their zone picker from the same list', function () {
        $views = __DIR__.'/../../resources/views/livewire/booking/partials/';

        foreach (['calendar-glass', 'calendar-light'] as $partial) {
            expect(file_get_contents($views.$partial.'.blade.php'))->toContain('x-for="choice in choices"');
        }

        expect((new MultistepBookingWizard)->calendarConfig()['choices'])->toBe(MultistepBookingWizard::TIMEZONE_CHOICES);
    });
});
