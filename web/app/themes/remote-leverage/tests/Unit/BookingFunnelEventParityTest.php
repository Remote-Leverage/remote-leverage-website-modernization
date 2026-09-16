<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;

/*
 * Booking-funnel event parity with the legacy form.
 *
 * rl-elementor-blocks' assets/js/headless-calendly-multistep.js fired these names client-side
 * via posthog.capture(). The PostHog funnels, Customer.io campaigns and n8n flows are keyed to
 * them, and the Livewire wizard briefly emitted `booking_wizard_`-prefixed names instead, which
 * meant every one of those consumers saw nothing.
 *
 * These tests pin the names. Renaming an event here is a downstream breaking change, not a
 * refactor — if one of these fails, the integrations have to move first.
 */

/** Capture every event the wizard emits, by swapping the recorder in the container. */
function captureWizardEvents(callable $exercise): array
{
    // A property, not a captured reference: PHP properties cannot be references, and an
    // invalid recorder fails *silently* here because trackStepEvent() swallows throwables to
    // keep analytics from breaking a booking. An empty result is the only symptom.
    $recorder = new class extends RecordBehaviorEventAction
    {
        /** @var AnalyticsEventData[] */
        public array $seen = [];

        public function __construct()
        {
            // Deliberately does not call parent::__construct(): the real gateways would try to
            // reach PostHog and Customer.io over the network.
        }

        public function execute(AnalyticsEventData $event): void
        {
            $this->seen[] = $event;
        }
    };

    app()->instance(RecordBehaviorEventAction::class, $recorder);

    try {
        // Tracking is dispatched `afterResponse()` in production so two outbound HTTP calls
        // never run inside a Livewire round trip — see MultistepBookingWizard::deferTracking().
        // Under the bare test container that seam runs inline, so events are observable here.
        $exercise();
    } finally {
        app()->forgetInstance(RecordBehaviorEventAction::class);
    }

    return $recorder->seen;
}

/** @return string[] */
function eventNames(array $events): array
{
    return array_map(static fn (AnalyticsEventData $e) => $e->event, $events);
}

describe('booking funnel emits the legacy event names', function () {
    test('selecting a time slot emits hour_selected with the slot', function () {
        $events = captureWizardEvents(function () {
            $wizard = new MultistepBookingWizard;
            $wizard->selectSlot('2026-10-01T15:00:00Z');
        });

        expect(eventNames($events))->toContain('hour_selected');

        $hourSelected = collect($events)->firstWhere('event', 'hour_selected');
        expect($hourSelected->properties['selected_time'])->toBe('2026-10-01T15:00:00Z');
    });

    test('the first property update emits form_started exactly once', function () {
        $events = captureWizardEvents(function () {
            $wizard = new MultistepBookingWizard;
            $wizard->updated('email');
            $wizard->updated('phone');
            $wizard->updated('company');
        });

        expect(array_count_values(eventNames($events))['form_started'] ?? 0)->toBe(1);
    });

    test('calendar paging and timezone do not count as starting the form', function () {
        $events = captureWizardEvents(function () {
            $wizard = new MultistepBookingWizard;
            $wizard->updated('timezone');
            $wizard->updated('currentMonth');
            $wizard->updated('currentYear');
        });

        expect(eventNames($events))->not->toContain('form_started');
    });

    test('every emitted event carries the legacy property shape', function () {
        $events = captureWizardEvents(function () {
            $wizard = new MultistepBookingWizard;
            $wizard->selectSlot('2026-10-01T15:00:00Z');
        });

        expect($events)->not->toBeEmpty();

        foreach ($events as $event) {
            expect($event->properties)
                ->toHaveKeys(['form_id', 'session_id', 'form_type', 'is_isolated']);
            expect($event->properties['form_type'])->toBe('multistep');
        }
    });

    test('no event is emitted under the booking_wizard_ prefix', function () {
        $events = captureWizardEvents(function () {
            $wizard = new MultistepBookingWizard;
            $wizard->selectSlot('2026-10-01T15:00:00Z');
            $wizard->updated('email');
        });

        foreach (eventNames($events) as $name) {
            expect($name)->not->toStartWith('booking_wizard_');
        }
    });
});

describe('the legacy event vocabulary is still emitted somewhere in the component', function () {
    test('all eight legacy names appear as emitted events', function () {
        // A source-level check: the lifecycle points for the remaining events (mount, step
        // advance, partial capture, submit) each need collaborators that a unit test would have
        // to stub wholesale. Pinning the literals still catches the rename that caused this.
        $source = file_get_contents(
            __DIR__.'/../../app/Application/Livewire/Booking/MultistepBookingWizard.php'
        );

        $legacy = [
            'form_loaded',
            'form_started',
            'step_date_selection',
            'hour_selected',
            'partial_form_submitted',
            'booking_request_sent',
            'booking_finished',
        ];

        foreach ($legacy as $name) {
            expect($source)->toContain("trackStepEvent('{$name}'");
        }
    });

    test('booking_request_sent is always paired with a terminal booking_finished', function () {
        $source = file_get_contents(
            __DIR__.'/../../app/Application/Livewire/Booking/MultistepBookingWizard.php'
        );

        // Both the booked route and the skipCalendar / job-applicant route must terminate, or
        // that route reads as a 100% drop-off after the request event.
        expect(substr_count($source, "trackStepEvent('booking_request_sent'"))->toBe(1);
        expect(substr_count($source, "trackStepEvent('booking_finished'"))->toBe(2);
    });
});
