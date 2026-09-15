<?php

declare(strict_types=1);

namespace App\Domains\Payment\Data;

/**
 * The embedded-card checkout funnel, one case per step that is reported to analytics.
 *
 * This is the reconstruction of the telemetry the legacy `rl_payment_gateway` Elementor widget
 * fired and that the port to `acf/payment-gateway` dropped. The legacy source recovered from is
 * `rl-elementor-blocks/assets/js/payment-gateway.js` (client) and
 * `rl-elementor-blocks/src/Integrations/StripeWebhookHandler.php` (server), which both fed
 * `LoggingProvider` via `POST /wp-json/rl/v1/log` and, on the client, Customer.io's
 * `cioanalytics.track` and PostHog's `capture`/`identify`.
 *
 * `eventName()` is what v2 sends. `legacyLogEvent()` and `legacyCustomerIoEvent()` record what
 * the legacy widget sent for the same step, so a downstream PostHog insight or Customer.io
 * campaign built on the old names can be remapped deliberately instead of by guesswork. A step
 * with `null` for both had **no legacy equivalent** and its name is newly proposed — see
 * `isRecoveredFromLegacy()`.
 *
 * Naming rules applied here:
 *  - Where the legacy Customer.io name was already clean Title Case it is kept **verbatim**
 *    (`Payment Attempted`, `Payment Succeeded`, `Payment Failed`), because n8n / Customer.io
 *    mappings key off those strings.
 *  - `form_started` is kept verbatim in its lowercase legacy spelling. It is not a
 *    payment-specific event: the legacy booking widgets fired it too and the production funnel
 *    report aggregates across it (`rl-elementor-blocks/src/Settings.php`, the
 *    `SUM(CASE WHEN event_name = 'form_started' ...)` column). Renaming it would silently
 *    detach the checkout from that report.
 *  - The legacy names that came out of PHP's `ucfirst()` on a snake_case key
 *    (`Payment Intent_loaded`, `Payment Partial_email_captured`) are tidied to real Title Case,
 *    since nothing downstream can sensibly be keyed to the underscore artefact.
 */
enum CheckoutFunnelStep: string
{
    /** The checkout block became visible to a real browser. */
    case GatewayViewed = 'gateway_viewed';

    /** The visitor touched the first field of the form. */
    case CheckoutStarted = 'checkout_started';

    /** A syntactically valid email was entered, before any payment attempt. */
    case EmailCaptured = 'email_captured';

    /** The server created a Stripe PaymentIntent and the card form can mount. */
    case IntentCreated = 'intent_created';

    /** The visitor pressed pay and `stripe.confirmPayment()` was called. */
    case PaymentSubmitted = 'payment_submitted';

    /** The charge went through. Fired from three places — see `confirmation_source`. */
    case PaymentSucceeded = 'payment_succeeded';

    /** The charge, or the step that leads to it, did not go through. */
    case PaymentFailed = 'payment_failed';

    /**
     * The event name v2 sends to both PostHog and Customer.io.
     */
    public function eventName(): string
    {
        return match ($this) {
            self::GatewayViewed => 'Payment Gateway Viewed',
            self::CheckoutStarted => 'form_started',
            self::EmailCaptured => 'Payment Partial Email Captured',
            self::IntentCreated => 'Payment Intent Loaded',
            self::PaymentSubmitted => 'Payment Attempted',
            self::PaymentSucceeded => 'Payment Succeeded',
            self::PaymentFailed => 'Payment Failed',
        };
    }

    /**
     * The `event` value the legacy client POSTed to `/wp-json/rl/v1/log`, or null where the
     * step is new in v2.
     */
    public function legacyLogEvent(): ?string
    {
        return match ($this) {
            self::GatewayViewed => null,
            self::CheckoutStarted => null, // PostHog-only in the legacy widget; never logged.
            self::EmailCaptured => 'payment_partial_email_captured',
            self::IntentCreated => 'payment_intent_loaded',
            self::PaymentSubmitted => 'payment_attempted',
            self::PaymentSucceeded => 'payment_succeeded',
            self::PaymentFailed => 'payment_failed',
        };
    }

    /**
     * The name the legacy client passed to `cioanalytics.track()`, or null where the step had
     * no Customer.io call.
     */
    public function legacyCustomerIoEvent(): ?string
    {
        return match ($this) {
            self::GatewayViewed => null,
            self::CheckoutStarted => null,
            self::EmailCaptured => 'Payment Partial_email_captured',
            self::IntentCreated => 'Payment Intent_loaded',
            self::PaymentSubmitted => 'Payment Attempted',
            self::PaymentSucceeded => 'Payment Succeeded',
            self::PaymentFailed => 'Payment Failed',
        };
    }

    /**
     * The name the legacy widget captured directly into PostHog, bypassing `logEvent()`.
     */
    public function legacyPostHogEvent(): ?string
    {
        return match ($this) {
            self::CheckoutStarted => 'form_started',
            default => null,
        };
    }

    /**
     * False means the name in `eventName()` is a v2 proposal with no legacy counterpart, not a
     * restoration. Nothing downstream can already be keyed to it.
     */
    public function isRecoveredFromLegacy(): bool
    {
        return $this->legacyLogEvent() !== null
            || $this->legacyCustomerIoEvent() !== null
            || $this->legacyPostHogEvent() !== null;
    }

    /**
     * Every step, in funnel order.
     *
     * @return array<int, self>
     */
    public static function funnel(): array
    {
        return [
            self::GatewayViewed,
            self::CheckoutStarted,
            self::EmailCaptured,
            self::IntentCreated,
            self::PaymentSubmitted,
            self::PaymentSucceeded,
            self::PaymentFailed,
        ];
    }
}
