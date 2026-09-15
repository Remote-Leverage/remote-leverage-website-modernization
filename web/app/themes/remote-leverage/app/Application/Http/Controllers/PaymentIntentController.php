<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Domains\Payment\Data\CheckoutFunnelStep;
use App\Domains\Payment\Services\CheckoutTelemetry;
use App\Domains\Payment\Services\PaymentGatewayBlockResolver;
use App\Domains\Payment\Services\StripePaymentIntentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Creates the Stripe PaymentIntent behind the acf/payment-gateway block.
 *
 * Ported from rl-elementor-blocks `Plugin::ajax_create_payment_intent()` (admin-ajax action
 * `rl_create_payment_intent`). The shape of the exchange is the same — the browser posts who
 * the customer is, the server decides what they are charged — but the amount now comes from
 * the Gutenberg block tree instead of Elementor's `_elementor_data`.
 *
 * SECURITY: there is no `amount` input. If PaymentGatewayBlockResolver cannot find the block,
 * the request is refused; it never falls back to anything the client sent.
 */
class PaymentIntentController
{
    /**
     * Abuse ceiling per client IP. An unauthenticated PaymentIntent endpoint is a card-testing
     * target, and the legacy AJAX action had only a nonce (which any page view hands out) in
     * front of it. Fails open on a cache error so a broken cache never blocks a real payment.
     */
    protected const MAX_ATTEMPTS = 20;

    protected const DECAY_SECONDS = 60;

    public function __construct(
        protected PaymentGatewayBlockResolver $resolver,
        protected StripePaymentIntentGateway $gateway,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if ($this->tooManyAttempts($request)) {
            return response()->json(['error' => 'Too many attempts. Please wait a moment and try again.'], 429);
        }

        $postId = (int) $request->input('post_id', 0);
        $blockIndex = (int) $request->input('block_index', 0);
        $blockId = $this->text($request->input('block_id', ''));

        $firstName = $this->text($request->input('first_name', ''));
        $lastName = $this->text($request->input('last_name', ''));
        $customerName = trim($firstName.' '.$lastName);
        $email = $this->email($request->input('email', ''));
        $phone = $this->text($request->input('phone', ''));

        if ($postId <= 0) {
            return response()->json(['error' => 'Could not determine the page this form belongs to.'], 422);
        }

        // --- The only source of truth for what is charged. ---
        $charge = $this->resolver->resolve($postId, $blockIndex, $blockId);

        if ($charge === null) {
            Log::error('PaymentIntentController: refusing to charge, block could not be resolved', [
                'post_id' => $postId,
                'block_index' => $blockIndex,
                'block_id' => $blockId,
            ]);

            CheckoutTelemetry::deferStep(CheckoutFunnelStep::PaymentFailed, [
                'widget_id' => $blockId,
                'post_id' => $postId,
                'block_index' => $blockIndex,
                'failure_stage' => 'intent',
                'error' => 'block_not_resolved',
                'severity' => 'error',
                'source' => 'server',
            ], $email);

            return response()->json(['error' => 'This payment form is not configured correctly. Please contact support.'], 422);
        }

        $description = str_replace(
            '{name}',
            $customerName !== '' ? $customerName : 'Customer',
            $charge['description_template'],
        );

        $intent = $this->gateway->createPaymentIntent(
            $charge['amount'],
            $charge['currency'],
            $description,
            [
                // Metadata keys are the legacy set verbatim — StripeWebhookController and the
                // downstream Customer.io / n8n automations read them by these names.
                'widget_id' => $blockId,
                'customer_name' => $customerName,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'source' => 'remote_leverage_theme',
                'page_url' => (string) (get_permalink($postId) ?: $request->headers->get('referer', '')),
                'post_id' => (string) $postId,
            ],
        );

        if (! ($intent['ok'] ?? false)) {
            Log::error('PaymentIntentController: Stripe did not return a PaymentIntent', [
                'post_id' => $postId,
                'email' => $email,
                'error' => $intent['error'] ?? 'unknown',
            ]);

            CheckoutTelemetry::deferStep(CheckoutFunnelStep::PaymentFailed, [
                'widget_id' => $blockId,
                'post_id' => $postId,
                'amount' => $charge['amount'] / 100,
                'currency' => $charge['currency'],
                'failure_stage' => 'intent',
                'error' => (string) ($intent['error'] ?? 'unknown'),
                'severity' => 'error',
                'source' => 'server',
            ], $email);

            return response()->json(['error' => $intent['error'] ?? 'Failed to initialize payment.'], 502);
        }

        Log::info('PaymentIntentController: payment intent created', [
            'intent_id' => $intent['id'] ?? 'unknown',
            'amount' => $charge['amount'],
            'currency' => $charge['currency'],
            'post_id' => $postId,
            'email' => $email,
        ]);

        CheckoutTelemetry::deferStep(CheckoutFunnelStep::IntentCreated, [
            'widget_id' => $blockId,
            'post_id' => $postId,
            'payment_intent_id' => (string) ($intent['id'] ?? ''),
            'amount' => $charge['amount'] / 100,
            'currency' => $charge['currency'],
            'customer_name' => $customerName,
            'customer_phone' => $phone,
            'source' => 'server',
        ], $email);

        return response()->json(['client_secret' => $intent['client_secret'] ?? '']);
    }

    protected function tooManyAttempts(Request $request): bool
    {
        try {
            $key = 'rl-payment-intent:'.($request->ip() ?? 'unknown');

            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                return true;
            }

            RateLimiter::hit($key, self::DECAY_SECONDS);
        } catch (\Throwable $e) {
            Log::warning('PaymentIntentController: rate limiter unavailable', ['error' => $e->getMessage()]);
        }

        return false;
    }

    protected function text(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return function_exists('sanitize_text_field') ? sanitize_text_field($value) : trim(strip_tags($value));
    }

    protected function email(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return function_exists('sanitize_email') ? sanitize_email($value) : trim($value);
    }
}
