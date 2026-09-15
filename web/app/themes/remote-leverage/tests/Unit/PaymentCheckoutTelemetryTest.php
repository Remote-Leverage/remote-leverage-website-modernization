<?php

declare(strict_types=1);

use App\Domains\Payment\Data\CheckoutFunnelStep;
use App\Domains\Payment\Services\CheckoutTelemetry;
use App\Domains\Tracking\Data\AnalyticsEventData;

/*
 * Checkout-funnel telemetry, rebuilt after the port from rl-elementor-blocks dropped it.
 *
 * Nothing here touches the network: CheckoutTelemetry::buildEvent() is pure, and the PostHog /
 * Customer.io dispatch it feeds is covered by TrackingSubscribersTest against mocks.
 */

describe('CheckoutFunnelStep event mapping', function () {
    test('every funnel step maps to a non-empty, unique event name', function () {
        $names = array_map(
            static fn (CheckoutFunnelStep $step) => $step->eventName(),
            CheckoutFunnelStep::funnel(),
        );

        expect($names)->not->toContain('');
        expect(array_unique($names))->toHaveCount(count($names));
        expect(CheckoutFunnelStep::funnel())->toHaveCount(count(CheckoutFunnelStep::cases()));
    });

    test('names recovered verbatim from the legacy widget are not renamed', function () {
        // These three were `'Payment ' + ucfirst(name)` in rl-elementor-blocks'
        // assets/js/payment-gateway.js and are what n8n / Customer.io are mapped to today.
        expect(CheckoutFunnelStep::PaymentSubmitted->eventName())->toBe('Payment Attempted')
            ->and(CheckoutFunnelStep::PaymentSucceeded->eventName())->toBe('Payment Succeeded')
            ->and(CheckoutFunnelStep::PaymentFailed->eventName())->toBe('Payment Failed');

        foreach ([CheckoutFunnelStep::PaymentSubmitted, CheckoutFunnelStep::PaymentSucceeded, CheckoutFunnelStep::PaymentFailed] as $step) {
            expect($step->eventName())->toBe($step->legacyCustomerIoEvent());
        }
    });

    test('form_started keeps its legacy lowercase spelling', function () {
        // Not payment-specific: the legacy booking widgets fired it too and production's funnel
        // report aggregates on the literal string. Title-casing it would detach the checkout.
        expect(CheckoutFunnelStep::CheckoutStarted->eventName())->toBe('form_started')
            ->and(CheckoutFunnelStep::CheckoutStarted->legacyPostHogEvent())->toBe('form_started');
    });

    test('steps tidied out of a ucfirst artefact still declare their legacy name', function () {
        expect(CheckoutFunnelStep::IntentCreated->eventName())->toBe('Payment Intent Loaded')
            ->and(CheckoutFunnelStep::IntentCreated->legacyCustomerIoEvent())->toBe('Payment Intent_loaded')
            ->and(CheckoutFunnelStep::IntentCreated->legacyLogEvent())->toBe('payment_intent_loaded');

        expect(CheckoutFunnelStep::EmailCaptured->eventName())->toBe('Payment Partial Email Captured')
            ->and(CheckoutFunnelStep::EmailCaptured->legacyLogEvent())->toBe('payment_partial_email_captured');
    });

    test('gateway viewed is declared as a v2 proposal, not a restoration', function () {
        // The legacy widget had no viewed event at all. Anything claiming to be "restored"
        // must have a legacy name to point at; this one must not pretend to.
        expect(CheckoutFunnelStep::GatewayViewed->isRecoveredFromLegacy())->toBeFalse()
            ->and(CheckoutFunnelStep::GatewayViewed->legacyLogEvent())->toBeNull()
            ->and(CheckoutFunnelStep::GatewayViewed->legacyCustomerIoEvent())->toBeNull();

        $recovered = array_filter(
            CheckoutFunnelStep::funnel(),
            static fn (CheckoutFunnelStep $step) => $step->isRecoveredFromLegacy(),
        );

        expect($recovered)->toHaveCount(6);
    });
});

describe('CheckoutTelemetry::buildEvent', function () {
    test('produces an AnalyticsEventData the Tracking domain can dispatch', function () {
        $event = CheckoutTelemetry::buildEvent(CheckoutFunnelStep::PaymentSucceeded, [
            'widget_id' => 'rl-payment-0',
            'post_id' => 38897,
            'amount' => 100.0,
            'currency' => 'usd',
            'confirmation_source' => 'stripe_webhook',
        ], 'Sarah.Connor@Cyberdyne.IO');

        expect($event)->toBeInstanceOf(AnalyticsEventData::class)
            ->and($event->event)->toBe('Payment Succeeded')
            ->and($event->distinctId)->toBe('sarah.connor@cyberdyne.io')
            ->and($event->timestamp)->toBeInt();

        expect($event->properties)->toMatchArray([
            'widget_id' => 'rl-payment-0',
            'post_id' => 38897,
            'amount' => 100.0,
            'currency' => 'usd',
            'confirmation_source' => 'stripe_webhook',
            'form_type' => 'payment_gateway',
            'funnel_step' => 'payment_succeeded',
            'email' => 'sarah.connor@cyberdyne.io',
        ]);
    });

    test('keys the event on the email so a checkout stitches onto the same PostHog person', function () {
        // Everything else in the codebase (HandleLeadCreatedForTracking, CalendlyWebhook-
        // Controller) uses the email as the distinct id. A different key here would create a
        // second profile and break the lead -> booking -> payment funnel.
        $event = CheckoutTelemetry::buildEvent(CheckoutFunnelStep::PaymentSubmitted, [], '  KYLE@reese.dev ');

        expect($event->distinctId)->toBe('kyle@reese.dev');
    });

    test('falls back to anonymous and drops the email property when there is no usable email', function () {
        foreach ([null, '', '   ', 'not-an-email', 12345] as $candidate) {
            $event = CheckoutTelemetry::buildEvent(CheckoutFunnelStep::GatewayViewed, ['email' => $candidate]);

            expect($event->distinctId)->toBe('anonymous')
                ->and($event->properties)->not->toHaveKey('email');
        }
    });

    test('reads the email out of the properties when none is passed explicitly', function () {
        $event = CheckoutTelemetry::buildEvent(CheckoutFunnelStep::EmailCaptured, ['email' => 'T800@skynet.mil']);

        expect($event->distinctId)->toBe('t800@skynet.mil')
            ->and($event->properties['email'])->toBe('t800@skynet.mil');
    });

    test('drops empty properties so an absent widget_id reads as absent, not as ""', function () {
        $event = CheckoutTelemetry::buildEvent(CheckoutFunnelStep::PaymentFailed, [
            'widget_id' => '',
            'card_brand' => null,
            'metadata' => [],
            'error' => 'Your card was declined.',
            'amount' => 0,
        ]);

        expect($event->properties)->not->toHaveKey('widget_id')
            ->and($event->properties)->not->toHaveKey('card_brand')
            ->and($event->properties)->not->toHaveKey('metadata')
            ->and($event->properties['error'])->toBe('Your card was declined.')
            // 0 is a real amount, not an empty value.
            ->and($event->properties)->toHaveKey('amount');
    });

    test('funnel_step cannot be overridden by caller-supplied properties', function () {
        $event = CheckoutTelemetry::buildEvent(CheckoutFunnelStep::PaymentFailed, [
            'funnel_step' => 'payment_succeeded',
        ]);

        expect($event->properties['funnel_step'])->toBe('payment_failed');
    });
});

describe('client and server halves agree on the event names', function () {
    test('resources/js/payment-gateway.js uses exactly the PHP enum names', function () {
        $js = file_get_contents(__DIR__.'/../../resources/js/payment-gateway.js');

        expect($js)->not->toBeFalse();

        // The JS FUNNEL map is the client-side mirror of CheckoutFunnelStep::eventName(). A
        // rename on one side only would split the funnel in PostHog without failing anything.
        preg_match('/const FUNNEL = \{(.*?)\};/s', (string) $js, $matches);

        // A missing map means the client-side mirror was removed or renamed.
        expect($matches)->toHaveKey(1);

        preg_match_all("/'([^']+)',?\s*$/m", $matches[1], $found);

        $jsNames = $found[1];

        // Every name the client fires must be a name the enum knows about.
        $phpNames = array_map(
            static fn (CheckoutFunnelStep $step) => $step->eventName(),
            CheckoutFunnelStep::funnel(),
        );

        expect($jsNames)->not->toBeEmpty();

        foreach ($jsNames as $name) {
            expect($phpNames)->toContain($name);
        }

        // And every step the client is responsible for must actually be in that map.
        foreach ([
            CheckoutFunnelStep::GatewayViewed,
            CheckoutFunnelStep::CheckoutStarted,
            CheckoutFunnelStep::EmailCaptured,
            CheckoutFunnelStep::PaymentSubmitted,
            CheckoutFunnelStep::PaymentSucceeded,
            CheckoutFunnelStep::PaymentFailed,
        ] as $step) {
            expect($jsNames)->toContain($step->eventName());
        }

        // The legacy PostHog-only event is kept verbatim.
        expect($js)->toContain("'payment_stripe_load_failed'");

        // And the endpoint that was deliberately not reinstated must stay gone from the code.
        // Comments are stripped first — the file explains at length why it is not there.
        $code = (string) preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $js);

        expect($code)->not->toContain('/wp-json/rl/v1/log');
    });

    test('no funnel telemetry posts to our own server from the browser', function () {
        $js = (string) file_get_contents(__DIR__.'/../../resources/js/payment-gateway.js');

        // The only fetch() calls the checkout may make are the PaymentIntent request and
        // intl-tel-input's geo lookup. A third would mean a new logging endpoint crept back in.
        preg_match_all('/fetch\(/', $js, $fetches);

        expect($fetches[0])->toHaveCount(2);
    });
});
