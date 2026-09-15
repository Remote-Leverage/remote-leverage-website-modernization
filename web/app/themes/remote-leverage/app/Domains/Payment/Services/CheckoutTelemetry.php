<?php

declare(strict_types=1);

namespace App\Domains\Payment\Services;

use App\Domains\Payment\Data\CheckoutFunnelStep;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Support\Facades\Log;

/**
 * Server-side half of the checkout funnel telemetry.
 *
 * Rebuilds what `rl-elementor-blocks` reported and the port to `acf/payment-gateway` dropped,
 * but through the Tracking domain rather than a parallel mechanism: every step becomes an
 * `AnalyticsEventData` handed to `RecordBehaviorEventAction`, the same dual-dispatch to PostHog
 * and Customer.io that `CalendlyWebhookController` and `HandleLeadCreatedForTracking` use.
 *
 * Deliberately NOT rebuilt: the legacy `POST /wp-json/rl/v1/log` endpoint and its
 * `LoggingProvider` table. It was `permission_callback => '__return_true'` — an unauthenticated
 * write endpoint sitting on the money path — and its three jobs are all covered:
 *  - funnel breadcrumbs → PostHog, via this class and its client-side mirror;
 *  - UTM / gclid / fbclid / PostHog session + replay URL → carried as event properties, so they
 *    ride along on the event instead of needing their own indexed columns;
 *  - operational record → `Log::` in `PaymentIntentController` / `StripeWebhookController`,
 *    which the port already added and which is the authoritative record of a charge.
 *
 * `rl_lead_activity_logs` (`LeadActivityLogger`) is the Tracking-adjacent audit log, and it is
 * NOT usable for these steps: its `lead_id` is a non-nullable foreign key onto `rl_leads`, and
 * an anonymous visitor part-way through a checkout has no Lead row. Writing one just to hold a
 * funnel breadcrumb would pollute the lead table that the booking dedup guard reads.
 *
 * LATENCY: nothing here may block a response. `deferStep()` wraps the dispatch in
 * `->afterResponse()` exactly as `LeadServiceProvider`/`TrackingServiceProvider` do, and for the
 * same reason — no queue worker is deployed (`queue.default` is `sync`), so an un-deferred
 * PostHog + Customer.io call pair would run inside the customer's request. The closure is
 * `static` and resolves through the global `app()` helper so it never captures `$this`; a
 * non-static closure here captures the whole container graph and fatally OOMs inside
 * serializable-closure, silently dropping the work. See LeadServiceProvider::boot().
 */
class CheckoutTelemetry
{
    public function __construct(
        protected RecordBehaviorEventAction $recordEventAction,
    ) {}

    /**
     * Record a funnel step after the response has been flushed to the browser.
     *
     * Never throws: a telemetry failure must not turn a successful payment into an error.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function deferStep(CheckoutFunnelStep $step, array $properties = [], ?string $email = null): void
    {
        $event = self::buildEvent($step, $properties, $email);

        try {
            dispatch(static fn () => app(self::class)->send($event))->afterResponse();
        } catch (\Throwable $e) {
            // No container, no queue binding, or a render outside a dispatched request. The
            // payment is unaffected; only the breadcrumb is lost.
            Log::warning('CheckoutTelemetry: could not defer '.$step->eventName().': '.$e->getMessage());
        }
    }

    /**
     * Dispatch an already-built event. Public because `deferStep()`'s static closure calls it
     * on a freshly resolved instance after the response has gone out.
     */
    public function send(AnalyticsEventData $event): void
    {
        try {
            $this->recordEventAction->execute($event);
        } catch (\Throwable $e) {
            Log::error('CheckoutTelemetry: dispatch failed for '.$event->event.': '.$e->getMessage());
        }
    }

    /**
     * Map a funnel step plus its context onto the Tracking domain's event shape.
     *
     * Pure: no config, no WordPress, no network — this is the part the tests assert on.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function buildEvent(CheckoutFunnelStep $step, array $properties = [], ?string $email = null): AnalyticsEventData
    {
        $email = self::normalizeEmail($email ?? ($properties['email'] ?? null));

        return AnalyticsEventData::fromArray([
            'event' => $step->eventName(),
            // PostHog and Customer.io are both keyed by email everywhere else in this codebase
            // (`HandleLeadCreatedForTracking`, `CalendlyWebhookController`), so an identified
            // checkout stitches onto the same person rather than a second anonymous profile.
            'distinct_id' => $email ?? 'anonymous',
            'properties' => self::properties($step, $properties, $email),
            'timestamp' => time(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected static function properties(CheckoutFunnelStep $step, array $properties, ?string $email): array
    {
        $merged = array_merge(
            [
                // Legacy `logEvent()` stamped these on every call; downstream keeps them.
                'form_type' => 'payment_gateway',
            ],
            $properties,
            [
                'funnel_step' => $step->value,
            ],
        );

        if ($email !== null) {
            $merged['email'] = $email;
        } else {
            unset($merged['email']);
        }

        // Drop empties so a missing widget_id/post_id reads as absent rather than as "".
        return array_filter(
            $merged,
            static fn ($value) => $value !== null && $value !== '' && $value !== [],
        );
    }

    protected static function normalizeEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = strtolower(trim($email));

        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        return $email;
    }
}
