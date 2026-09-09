<?php

declare(strict_types=1);

namespace App\Domains\Referral\Services;

use App\Domains\Referral\Models\Payout;
use App\Domains\Referral\Models\Referrer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeConnectGateway
{
    protected ?string $secretKey;

    protected ?string $clientId;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        $this->clientId = config('services.stripe.client_id');
    }

    /**
     * Create an onboarding link for a referrer.
     */
    public function createOnboardingLink(Referrer $referrer, string $returnUrl, string $refreshUrl): ?string
    {
        if (! $this->secretKey) {
            Log::warning('StripeConnectGateway: Missing Stripe secret key.');

            return null;
        }

        try {
            // If the referrer doesn't have a Stripe account yet, create one
            $accountId = $referrer->stripe_account_id;
            if (! $accountId) {
                $accountResponse = Http::withToken($this->secretKey)
                    ->asForm()
                    ->post('https://api.stripe.com/v1/accounts', [
                        'type' => 'express',
                        'email' => $referrer->email,
                        'capabilities' => [
                            'transfers' => ['requested' => 'true'],
                        ],
                        'metadata' => [
                            'referrer_id' => (string) $referrer->id,
                            'referral_code' => $referrer->referral_code,
                        ],
                    ]);

                if ($accountResponse->failed()) {
                    Log::error('StripeConnectGateway: Failed to create account', $accountResponse->json());

                    return null;
                }

                $accountId = $accountResponse->json('id');
                $referrer->update(['stripe_account_id' => $accountId]);
            }

            // Create account link for onboarding
            $linkResponse = Http::withToken($this->secretKey)
                ->asForm()
                ->post('https://api.stripe.com/v1/account_links', [
                    'account' => $accountId,
                    'refresh_url' => $refreshUrl,
                    'return_url' => $returnUrl,
                    'type' => 'account_onboarding',
                ]);

            if ($linkResponse->failed()) {
                Log::error('StripeConnectGateway: Failed to create onboarding link', $linkResponse->json());

                return null;
            }

            return $linkResponse->json('url');
        } catch (\Throwable $e) {
            Log::error('StripeConnectGateway Exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Transfer funds to a referrer's connected Stripe account.
     */
    public function transferPayout(Payout $payout): ?string
    {
        if (! $this->secretKey) {
            Log::warning('StripeConnectGateway: Missing Stripe secret key.');

            return null;
        }

        $referrer = $payout->referrer;
        if (! $referrer || ! $referrer->stripe_account_id) {
            Log::error('StripeConnectGateway: Cannot transfer payout, referrer has no connected Stripe account', [
                'payout_id' => $payout->id,
            ]);

            return null;
        }

        try {
            $amountInCents = (int) round($payout->amount * 100);

            $response = Http::withToken($this->secretKey)
                ->asForm()
                ->post('https://api.stripe.com/v1/transfers', [
                    'amount' => $amountInCents,
                    'currency' => strtolower($payout->currency),
                    'destination' => $referrer->stripe_account_id,
                    'description' => 'Remote Leverage Referrer Payout #'.$payout->id,
                    'metadata' => [
                        'payout_id' => (string) $payout->id,
                        'referrer_id' => (string) $referrer->id,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('StripeConnectGateway: Transfer failed', $response->json());

                return null;
            }

            $transferId = $response->json('id');
            $payout->update([
                'stripe_transfer_id' => $transferId,
                'status' => 'completed',
            ]);

            return $transferId;
        } catch (\Throwable $e) {
            Log::error('StripeConnectGateway Transfer Exception: '.$e->getMessage());

            return null;
        }
    }
}
