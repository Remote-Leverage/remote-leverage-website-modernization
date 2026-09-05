<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Domains\Referral\Models\Partner;
use App\Domains\Referral\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController
{
    /**
     * Handle incoming Stripe webhook payload.
     */
    public function handle(Request $request): JsonResponse
    {
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
        Partner::query()->where('stripe_account_id', $accountId)->update([
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
}
