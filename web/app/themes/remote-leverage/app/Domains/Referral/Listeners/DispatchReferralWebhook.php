<?php

declare(strict_types=1);

namespace App\Domains\Referral\Listeners;

use App\Domains\Referral\Events\ReferralRecorded;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchReferralWebhook
{
    public function handle(ReferralRecorded $event): void
    {
        $referral = $event->referral;

        Log::info('Referral webhook dispatched', [
            'referral_id' => $referral->id,
            'partner_id' => $referral->partner_id,
            'status' => $referral->status,
        ]);

        $webhookUrl = config('services.referral.webhook_url');
        if ($webhookUrl) {
            try {
                Http::timeout(5)->post($webhookUrl, [
                    'event' => 'referral.recorded',
                    'referral' => $referral->toArray(),
                ]);
            } catch (\Throwable $e) {
                Log::error('DispatchReferralWebhook failed: '.$e->getMessage());
            }
        }
    }
}
