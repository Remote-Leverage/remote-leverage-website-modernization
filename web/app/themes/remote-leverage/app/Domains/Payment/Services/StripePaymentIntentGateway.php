<?php

declare(strict_types=1);

namespace App\Domains\Payment\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Creates Stripe PaymentIntents for the embedded card checkout.
 *
 * Ported from rl-elementor-blocks `src/Integrations/StripeIntegration.php`. The legacy class
 * read its keys from WP options (`rl_stripe_test_mode`, `rl_stripe_live_secret_key`,
 * `rl_stripe_test_secret_key`); v2 reads the same pair from config/services.php, which reads
 * env. Everything else — endpoint, form encoding, `automatic_payment_methods[enabled]`,
 * `receipt_email` derived from metadata, error shape — is unchanged, because this is the
 * money path and Stripe's response contract is what the client script consumes.
 */
class StripePaymentIntentGateway
{
    /**
     * Stripe REST endpoint. Hardcoded rather than configurable: a configurable payment host
     * is an exfiltration target, and there is no legitimate reason to point this elsewhere.
     */
    protected const ENDPOINT = 'https://api.stripe.com/v1/payment_intents';

    public function isTestMode(): bool
    {
        return (bool) config('services.stripe.test_mode', false);
    }

    /**
     * Publishable key for the browser (Stripe Elements). Safe to render into the page.
     */
    public function publishableKey(): string
    {
        $key = $this->isTestMode()
            ? config('services.stripe.test_publishable_key')
            : config('services.stripe.live_publishable_key');

        return is_string($key) ? trim($key) : '';
    }

    /**
     * Secret key for server-to-server calls. Never leaves this class.
     */
    protected function secretKey(): string
    {
        $key = $this->isTestMode()
            ? config('services.stripe.test_secret_key')
            : config('services.stripe.live_secret_key');

        return is_string($key) ? trim($key) : '';
    }

    /**
     * Whether the secret key for the active mode is present, without exposing it.
     */
    public function isConfigured(): bool
    {
        return $this->secretKey() !== '';
    }

    /**
     * Create a PaymentIntent.
     *
     * @param  int  $amount  Amount in the currency's minor unit (e.g. 10000 for $100.00).
     * @param  string  $currency  ISO currency code.
     * @param  string  $description  Description shown in the Stripe dashboard.
     * @param  array<string, string>  $metadata  Metadata attached to the intent.
     * @return array{ok: bool, client_secret?: string, id?: string, error?: string}
     */
    public function createPaymentIntent(int $amount, string $currency, string $description, array $metadata = []): array
    {
        $secretKey = $this->secretKey();

        if ($secretKey === '') {
            Log::error('StripePaymentIntentGateway: Stripe secret key is not configured.', [
                'test_mode' => $this->isTestMode(),
            ]);

            return ['ok' => false, 'error' => 'Stripe Secret Key is not configured.'];
        }

        $body = [
            'amount' => $amount,
            'currency' => strtolower($currency),
            'description' => $description,
            'metadata' => $metadata,
            'automatic_payment_methods[enabled]' => 'true',
        ];

        // Attach receipt_email so Stripe auto-sends a receipt (legacy parity).
        if (! empty($metadata['customer_email'])) {
            $body['receipt_email'] = $metadata['customer_email'];
        }

        try {
            // asForm() would re-encode `metadata` as metadata[key]=value, which is exactly
            // what Stripe wants, but http_build_query is what the legacy plugin sent and
            // produces the identical wire format — kept explicit so the encoding of nested
            // metadata is not left to a client default.
            $response = Http::withToken($secretKey)
                ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->timeout(15)
                ->withBody(http_build_query($body), 'application/x-www-form-urlencoded')
                ->post(self::ENDPOINT);
        } catch (\Throwable $e) {
            Log::error('StripePaymentIntentGateway: request failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Could not reach the payment processor. Please try again.'];
        }

        $data = $response->json();

        if (! is_array($data)) {
            $data = [];
        }

        if ($response->status() !== 200) {
            $message = $data['error']['message'] ?? 'Unknown Stripe error.';

            Log::error('StripePaymentIntentGateway: Stripe rejected the PaymentIntent', [
                'status' => $response->status(),
                'stripe_error' => $data['error'] ?? null,
            ]);

            return ['ok' => false, 'error' => (string) $message];
        }

        return [
            'ok' => true,
            'client_secret' => (string) ($data['client_secret'] ?? ''),
            'id' => (string) ($data['id'] ?? ''),
        ];
    }
}
