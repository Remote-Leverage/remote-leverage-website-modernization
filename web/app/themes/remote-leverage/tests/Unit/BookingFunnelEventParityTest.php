<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Domains\Tracking\Data\AnalyticsEventData;
use App\Domains\Tracking\Gateways\CustomerIOClient;

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

/**
 * Capture every event the wizard emits, by swapping the Customer.io gateway in the container.
 *
 * Customer.io is the only destination the component still sends to from PHP. PostHog is captured
 * in the browser (see `postHogCaptureExpression()` and the assertions further down), so it is not
 * observable here — but both are driven from the same `trackStepEvent()` call with the same name
 * and the same payload, which is what these tests pin.
 *
 * **The wizard must hold an email for anything to arrive here.** Customer.io is keyed to email
 * and the wizard now sends nothing without one, so a wizard built inside `$exercise` has to be
 * given one first — `bookingWizardWithEmail()` does that. Without it these tests pass vacuously
 * on an empty array, which is why the two that assert an *absence* use it as well. See
 * `CustomerIOClient::track()` for why the emailless events are dropped rather than sent under a
 * stand-in id.
 */
function captureWizardEvents(callable $exercise): array
{
    // A property, not a captured reference: PHP properties cannot be references, and an
    // invalid recorder fails *silently* here because trackStepEvent() swallows throwables to
    // keep analytics from breaking a booking. An empty result is the only symptom.
    $recorder = new class extends CustomerIOClient
    {
        /** @var AnalyticsEventData[] */
        public array $seen = [];

        public function __construct()
        {
            // Deliberately does not call parent::__construct(): the real gateway would try to
            // reach Customer.io over the network.
        }

        public function track(AnalyticsEventData $event): bool
        {
            $this->seen[] = $event;

            return true;
        }
    };

    app()->instance(CustomerIOClient::class, $recorder);

    try {
        // Tracking is dispatched `afterResponse()` in production so an outbound HTTP call never
        // runs inside a Livewire round trip — see MultistepBookingWizard::deferTracking().
        // Under the bare test container that seam runs inline, so events are observable here.
        $exercise();
    } finally {
        app()->forgetInstance(CustomerIOClient::class);
    }

    return $recorder->seen;
}

/** @return string[] */
function eventNames(array $events): array
{
    return array_map(static fn (AnalyticsEventData $e) => $e->event, $events);
}

/**
 * A wizard past step 1, which is the only state in which Customer.io is a destination.
 *
 * This is not a convenience for the harness — it is the real funnel. Step 1 collects the email,
 * so every event these tests pin except `form_loaded` genuinely happens with one in hand.
 */
function bookingWizardWithEmail(): MultistepBookingWizard
{
    $wizard = new MultistepBookingWizard;
    $wizard->email = 'lead@example.com';

    return $wizard;
}

describe('booking funnel emits the legacy event names', function () {
    test('selecting a time slot emits hour_selected with the slot', function () {
        $events = captureWizardEvents(function () {
            $wizard = bookingWizardWithEmail();
            $wizard->selectSlot('2026-10-01T15:00:00Z');
        });

        expect(eventNames($events))->toContain('hour_selected');

        $hourSelected = collect($events)->firstWhere('event', 'hour_selected');
        expect($hourSelected->properties['selected_time'])->toBe('2026-10-01T15:00:00Z');
    });

    test('the first property update emits form_started exactly once', function () {
        $events = captureWizardEvents(function () {
            $wizard = bookingWizardWithEmail();
            $wizard->updated('email');
            $wizard->updated('phone');
            $wizard->updated('company');
        });

        expect(array_count_values(eventNames($events))['form_started'] ?? 0)->toBe(1);
    });

    test('the zone and the calendar\'s picks do not count as starting the form', function () {
        // Month paging no longer reaches the server at all; these are the values the browser
        // calendar and the page-load zone detection send along with other requests.
        $events = captureWizardEvents(function () {
            $wizard = bookingWizardWithEmail();
            $wizard->updated('timezone');
            $wizard->updated('browserTimezone');
            $wizard->updated('selectedDate');
            $wizard->updated('selectedSlot');
        });

        expect(eventNames($events))->not->toContain('form_started');
    });

    test('every emitted event carries the legacy property shape', function () {
        $events = captureWizardEvents(function () {
            $wizard = bookingWizardWithEmail();
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
            $wizard = bookingWizardWithEmail();
            $wizard->selectSlot('2026-10-01T15:00:00Z');
            $wizard->updated('email');
        });

        foreach (eventNames($events) as $name) {
            expect($name)->not->toStartWith('booking_wizard_');
        }
    });
});

describe('Customer.io only hears about people it can reach', function () {
    /*
     * The workspace filled with thousands of emailless profiles under 40-character ids.
     *
     * `form_loaded` fires from mount(), and the wizard renders on the front page, every single
     * post and the booking footer — so this was close to one profile per visitor per session.
     * The id was the Laravel session id, which the Track API's events endpoint happily *creates*
     * a customer for; it also rolled with the session and was replaced by the email once step 1
     * completed, so the earlier steps were stranded on a profile nothing would ever merge.
     */
    test('no event reaches Customer.io before the email is known', function () {
        $events = captureWizardEvents(function () {
            $wizard = new MultistepBookingWizard;   // mount() emits form_loaded
            $wizard->updated('phone');              // and this emits form_started
            $wizard->selectSlot('2026-10-01T15:00:00Z');
        });

        expect($events)->toBeEmpty();
    });

    test('every event that does reach Customer.io is keyed to an email', function () {
        $events = captureWizardEvents(function () {
            $wizard = bookingWizardWithEmail();
            $wizard->selectSlot('2026-10-01T15:00:00Z');
            $wizard->updated('email');
        });

        expect($events)->not->toBeEmpty();

        foreach ($events as $event) {
            expect(filter_var($event->distinctId, FILTER_VALIDATE_EMAIL))->not->toBeFalse();
        }
    });

    test('the gateway itself refuses an identifier that is not an email', function () {
        // The container swap above cannot catch a caller that builds its own client, and
        // CheckoutTelemetry and the Calendly webhook both used to fall back to literal
        // `anonymous` / `unknown` strings — one shared profile pooling unrelated people.
        $client = new CustomerIOClient;

        foreach (['anonymous', 'unknown', '', 'DvqJ50VGx659N5arXKXej60HA9jyRcUFY0IkEwS7'] as $id) {
            expect($client->track(new AnalyticsEventData(event: 'step_viewed', distinctId: $id)))
                ->toBeFalse();
        }
    });
});

describe('PostHog is captured in the browser, not from PHP', function () {
    /*
     * The funnel went silent at cutover because these events were moved server-side. A server
     * capture has no person, no $current_url and no session to attach to, and the booking form
     * never called identify() at all — so PostHog had nothing to file the events against.
     *
     * These assertions are on the JS the component hands the browser. Reverting to a PHP
     * capture, or dropping the identify, has to fail here.
     */
    test('a funnel event is handed to the browser as a posthog.capture call', function () {
        $wizard = new MultistepBookingWizard;

        $expression = (fn () => $this->postHogCaptureExpression('hour_selected', [
            'form_type' => 'multistep',
            'selected_time' => '2026-10-01T15:00:00Z',
            'selected_date' => null,
        ]))->call($wizard);

        expect($expression)
            ->toContain('window.posthog.capture(')
            ->toContain('"hour_selected"')
            ->toContain('"selected_time":"2026-10-01T15:00:00Z"')
            // Guarded, because PostHog is absent for anyone blocking it and a bare call would
            // throw inside Livewire's effect runner and take the rest of the effects with it.
            ->toContain('window.posthog && typeof window.posthog.capture === "function"')
            // Nulls stripped: an untouched field must not land as an empty property.
            ->not->toContain('selected_date');
    });

    test('the visitor is identified by email, with the funnel person properties', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->email = 'lead@example.com';
        $wizard->name = 'Ada Lovelace';
        $wizard->roleNeeded = 'Executive Assistant';

        $expression = (fn () => $this->postHogIdentifyExpression())->call($wizard);

        expect($expression)
            ->toContain('window.posthog.identify("lead@example.com"')
            ->toContain('"name":"Ada Lovelace"')
            ->toContain('"role_needed":"Executive Assistant"');
    });

    test('identify is sent once and only once the email is known', function () {
        $wizard = new MultistepBookingWizard;

        // No email yet: nothing to identify with, and identifying on a blank string would
        // merge every anonymous visitor into one person.
        (fn () => $this->identifyInBrowser())->call($wizard);
        expect($wizard->identifiedInBrowser)->toBeFalse();

        $wizard->email = 'lead@example.com';
        (fn () => $this->identifyInBrowser())->call($wizard);
        expect($wizard->identifiedInBrowser)->toBeTrue();
    });

    test('no funnel event is captured into PostHog from PHP', function () {
        $source = file_get_contents(
            __DIR__.'/../../app/Application/Livewire/Booking/MultistepBookingWizard.php'
        );

        // RecordBehaviorEventAction is the dual-dispatch action: PostHog *and* Customer.io.
        // The wizard must reach Customer.io directly, or PostHog gets a second, orphan copy of
        // every event under a distinct id the browser has never heard of.
        expect($source)->not->toContain('RecordBehaviorEventAction');
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

describe('revenue-band pricing warning', function () {
    // The test bootstrap starts with empty config, so load the file the application ships —
    // these assertions are about the real copy, not a fixture of it.
    beforeEach(function () {
        config(['booking' => require __DIR__.'/../../config/booking.php']);
    });

    test('the $0-5k band shows a warning before the calendar', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->monthlyRevenue = '$0 to $5k Per Month';

        $warning = $wizard->warningForBand();

        expect($warning)->not->toBeNull()
            ->and($warning['warning_heading'])->toContain('too expensive')
            ->and($warning['warning_button'])->toBe('Continue');
    });

    test('the pricing actually quoted matches production', function () {
        // These numbers are the offer. A silent edit here misquotes every low-revenue lead.
        $wizard = new MultistepBookingWizard;
        $wizard->monthlyRevenue = '$0 to $5k Per Month';

        $body = collect($wizard->warningForBand()['warning_body'])->pluck('text')->filter()->implode(' ');

        expect($body)->toContain('40% of the annual VA salary')
            ->and($body)->toContain('$4000 to $6000')
            ->and($body)->toContain('pay your VA directly');
    });

    test('every other band goes straight through', function () {
        foreach (['$5k to $10k Per Month', '$10k to $50k Per Month', '$50k-$100k Per Month', '$100k+ Per Month'] as $band) {
            $wizard = new MultistepBookingWizard;
            $wizard->monthlyRevenue = $band;

            expect($wizard->warningForBand())->toBeNull();
        }
    });

    test('an unrecognised band shows no warning rather than erroring', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->monthlyRevenue = 'Something marketing added last week';

        expect($wizard->warningForBand())->toBeNull();
    });

    test('dismissing the warning returns to step 1', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->monthlyRevenue = '$0 to $5k Per Month';
        $wizard->showWarning = true;
        $wizard->currentStep = 1;

        $wizard->dismissWarning();

        expect($wizard->showWarning)->toBeFalse()
            ->and($wizard->currentStep)->toBe(1);
    });
});

describe('warning acknowledgement', function () {
    beforeEach(function () {
        config(['booking' => require __DIR__.'/../../config/booking.php']);
    });

    test('accepting the warning records it so the visitor is not bounced back', function () {
        // The bug this pins: acknowledgeWarning() cleared showWarning and called goToStep(2),
        // which re-ran the step-1 check and set showWarning straight back to true. Continue
        // appeared to do nothing.
        $wizard = new MultistepBookingWizard;
        $wizard->monthlyRevenue = '$0 to $5k Per Month';
        $wizard->showWarning = true;

        expect($wizard->warningAcknowledged)->toBeFalse();

        $wizard->acknowledgeWarning();

        expect($wizard->warningAcknowledged)->toBeTrue()
            ->and($wizard->showWarning)->toBeFalse()
            // Advanced directly, without re-running step 1's gates.
            ->and($wizard->currentStep)->toBe(2);
    });

    test('backing out is not acknowledgement', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->monthlyRevenue = '$0 to $5k Per Month';
        $wizard->showWarning = true;
        $wizard->warningAcknowledged = true;

        $wizard->dismissWarning();

        expect($wizard->warningAcknowledged)->toBeFalse();
    });

    test('changing the revenue band re-arms the warning', function () {
        // Otherwise someone who accepts on $0-5k, goes back, and picks a band with a different
        // warning would never see it.
        $wizard = new MultistepBookingWizard;
        $wizard->warningAcknowledged = true;

        $wizard->monthlyRevenue = '$10k to $50k Per Month';
        $wizard->updatedMonthlyRevenue();

        expect($wizard->warningAcknowledged)->toBeFalse();
    });
});

/*
 * The thank-you redirect's capitalisation is part of the same contract.
 *
 * GTM container GTM-53JDTQCZ fires GA4 `appointment_booked`, Google Ads conversions
 * `AyW6CJHZnr8ZEOWU8r4q` and `pTbrCP6-_dIbEOWU8r4q`, and a PostHog `appointment_booked_web`
 * capture off a `Page Path contains VAThankYou` trigger. The predicate compiles to a bare `_cn`
 * with no ignore-case flag, so it is case-sensitive.
 *
 * Gravity Forms redirected to `/VAThankYou`, which WordPress serves with the capitals intact.
 * Lowercase serves the byte-identical page and matches nothing — so "tidying" this to
 * `/vathankyou/` silently switches off the primary conversion event and two live Ads
 * conversions, with no error anywhere. Audited 2026-09-17.
 */
test('the post-booking redirect keeps the capitalisation the GTM trigger matches on', function () {
    $source = file_get_contents(
        __DIR__.'/../../app/Application/Livewire/Booking/MultistepBookingWizard.php'
    );

    preg_match_all("/redirect\(home_url\('([^']+)'\)\)/", (string) $source, $matches);

    expect($matches[1])->not->toBeEmpty('the wizard should still redirect after a booking');

    $thankYou = array_values(array_filter(
        $matches[1],
        static fn (string $path): bool => stripos($path, 'thankyou') !== false,
    ));

    expect($thankYou)->not->toBeEmpty()
        ->and($thankYou[0])->toContain('VAThankYou')
        // Not merely case-insensitively present: the literal the trigger compares against.
        ->and(str_contains($thankYou[0], 'vathankyou'))->toBeFalse();
});
