<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Support\WebhookSignature;
use App\Domains\Referral\Models\Payout;
use App\Domains\Referral\Models\Referrer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe webhook receiver.
 *
 * Handles Connect events for referrer payouts (account.updated, transfer.*) and, ported from
 * rl-elementor-blocks `src/Integrations/StripeWebhookHandler.php`, `payment_intent.succeeded`
 * for the embedded card checkout: the PaymentIntent is flattened into the enriched payload
 * the downstream automations (n8n / Customer.io) expect, and forwarded to
 * `services.stripe.webhook_forward_url`.
 *
 * Signature verification is mandatory and fails closed: without a configured signing secret the
 * endpoint refuses every request rather than processing it. This endpoint forwards its input to
 * an external system, so an unverified "payment succeeded" would drive real onboarding.
 */
class StripeWebhookController
{
    /**
     * Handle incoming Stripe webhook payload.
     */
    public function handle(Request $request): JsonResponse
    {
        $webhookSecret = (string) (config('services.stripe.webhook_secret') ?? '');

        if ($webhookSecret === '') {
            // Fail closed. The legacy rl-elementor-blocks handler logged a warning and processed
            // anyway, which meant anyone who knew the URL could post a fabricated
            // `payment_intent.succeeded` and drive onboarding. An unconfigured environment now
            // goes dark instead of going open: set STRIPE_WEBHOOK_SECRET everywhere.
            Log::critical('Stripe Webhook: refused, no signing secret configured (STRIPE_WEBHOOK_SECRET).');

            return response()->json(['error' => 'Webhook signing secret is not configured.'], 503);
        }

        $error = WebhookSignature::verify(
            $request->getContent(),
            (string) $request->header('Stripe-Signature', ''),
            $webhookSecret,
        );

        if ($error !== null) {
            Log::error('Stripe Webhook: signature verification failed', ['error' => $error]);

            return response()->json(['error' => 'Signature verification failed.'], 403);
        }

        $payload = $request->all();
        $eventType = $payload['type'] ?? 'unknown';

        Log::info('Stripe Webhook Received: '.$eventType, ['event' => $eventType]);

        switch ($eventType) {
            case 'account.updated':
                $this->handleAccountUpdated($payload['data']['object'] ?? []);
                break;

            case 'transfer.paid':
                $this->handleTransferPaid($payload['data']['object'] ?? []);
                break;

            case 'transfer.failed':
                $this->handleTransferFailed($payload['data']['object'] ?? []);
                break;

            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($payload['data']['object'] ?? []);
                break;
        }

        return response()->json(['status' => 'success']);
    }

    protected function handleAccountUpdated(array $account): void
    {
        $accountId = $account['id'] ?? null;
        if (! $accountId) {
            return;
        }

        $payoutsEnabled = ! empty($account['payouts_enabled']);
        Referrer::query()->where('stripe_account_id', $accountId)->update([
            'status' => $payoutsEnabled ? 'active' : 'pending',
        ]);
    }

    protected function handleTransferPaid(array $transfer): void
    {
        $transferId = $transfer['id'] ?? null;
        if (! $transferId) {
            return;
        }

        Payout::query()->where('stripe_transfer_id', $transferId)->update([
            'status' => 'completed',
        ]);
    }

    protected function handleTransferFailed(array $transfer): void
    {
        $transferId = $transfer['id'] ?? null;
        if (! $transferId) {
            return;
        }

        Payout::query()->where('stripe_transfer_id', $transferId)->update([
            'status' => 'failed',
            'notes' => $transfer['failure_message'] ?? 'Transfer failed',
        ]);
    }

    /**
     * Enrich and forward a successful card payment.
     *
     * @param  array<string, mixed>  $paymentIntent
     */
    protected function handlePaymentIntentSucceeded(array $paymentIntent): void
    {
        if (empty($paymentIntent['id'])) {
            Log::warning('Stripe Webhook: payment_intent.succeeded with no payment intent data.');

            return;
        }

        $enriched = $this->buildEnrichedPayload('payment_intent.succeeded', $paymentIntent);

        Log::info('Stripe Webhook: payment succeeded', [
            'payment_intent_id' => $enriched['payment_intent_id'],
            'amount' => $enriched['amount'],
            'currency' => $enriched['currency'],
            'customer_email' => $enriched['customer_email'],
            'widget_id' => $enriched['widget_id'],
        ]);

        $forwardUrl = (string) (config('services.stripe.webhook_forward_url') ?? '');

        if ($forwardUrl === '') {
            Log::info('Stripe Webhook: no forward URL configured, payment not forwarded.');

            return;
        }

        $this->forwardToExternal($forwardUrl, $enriched);
    }

    /**
     * Flatten a Stripe PaymentIntent into the payload the onboarding automations consume.
     *
     * Keys are the legacy set verbatim — renaming one silently breaks whatever is mapped on
     * the n8n / Customer.io side.
     *
     * @param  array<string, mixed>  $pi
     * @return array<string, mixed>
     */
    protected function buildEnrichedPayload(string $eventType, array $pi): array
    {
        $metadata = is_array($pi['metadata'] ?? null) ? $pi['metadata'] : [];

        $paymentMethodDetails = [];
        $receiptUrl = '';
        $cardBrand = '';
        $cardLast4 = '';
        $paymentMethodType = '';

        // Stripe removed `charges` from the PaymentIntent in newer API versions in favour of
        // `latest_charge`; the legacy handler only read `charges.data`, and this keeps that
        // read exactly, so card brand/last4/receipt stay empty on a newer API version rather
        // than changing shape. See docs/stripe-payments.md.
        if (! empty($pi['charges']['data']) && is_array($pi['charges']['data'])) {
            $charge = $pi['charges']['data'][0] ?? [];
            $paymentMethodDetails = is_array($charge['payment_method_details'] ?? null)
                ? $charge['payment_method_details']
                : [];
            $receiptUrl = (string) ($charge['receipt_url'] ?? '');
        }

        if ($paymentMethodDetails !== []) {
            $paymentMethodType = (string) ($paymentMethodDetails['type'] ?? '');

            if ($paymentMethodType === 'card' && ! empty($paymentMethodDetails['card'])) {
                $cardBrand = (string) ($paymentMethodDetails['card']['brand'] ?? '');
                $cardLast4 = (string) ($paymentMethodDetails['card']['last4'] ?? '');
            }
        }

        return [
            'event' => $eventType,
            'payment_intent_id' => $pi['id'] ?? '',
            'amount' => isset($pi['amount']) ? ((float) $pi['amount'] / 100) : 0,
            'currency' => $pi['currency'] ?? '',
            'status' => $pi['status'] ?? '',
            'customer_name' => $metadata['customer_name'] ?? '',
            'customer_email' => $metadata['customer_email'] ?? ($pi['receipt_email'] ?? ''),
            'customer_phone' => $metadata['customer_phone'] ?? '',
            'description' => $pi['description'] ?? '',
            'payment_method_type' => $paymentMethodType,
            'card_brand' => $cardBrand,
            'card_last4' => $cardLast4,
            'widget_id' => $metadata['widget_id'] ?? '',
            'post_id' => $metadata['post_id'] ?? '',
            'page_url' => $metadata['page_url'] ?? '',
            'stripe_receipt_url' => $receiptUrl,
            'created_at' => isset($pi['created']) ? gmdate('c', (int) $pi['created']) : '',
            'metadata' => $metadata,
        ];
    }

    /**
     * Forward the enriched payload to the external webhook. Fire-and-forget with logging:
     * a failure here must never turn into a non-2xx back to Stripe, which would make Stripe
     * retry a payment we have already accepted.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function forwardToExternal(string $url, array $payload): void
    {
        try {
            $response = Http::timeout(15)->asJson()->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Stripe Webhook: forward failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'payment_intent_id' => $payload['payment_intent_id'] ?? '',
            ]);

            return;
        }

        $context = [
            'url' => $url,
            'status_code' => $response->status(),
            'response_body' => mb_substr((string) $response->body(), 0, 500),
            'payment_intent_id' => $payload['payment_intent_id'] ?? '',
        ];

        if ($response->successful()) {
            Log::info('Stripe Webhook: forward succeeded', $context);

            return;
        }

        Log::error('Stripe Webhook: forward rejected by target', $context);
    }
}
