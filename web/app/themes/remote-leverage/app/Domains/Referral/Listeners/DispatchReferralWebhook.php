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

        // `partner_id` used to be logged here. No such column exists on `rl_referrals` and
        // none ever has, so Eloquent returned null for it on every dispatch — the log line
        // read as "this referral has no partner" rather than as a typo.
        Log::info('Referral webhook dispatched', [
            'referral_id' => $referral->id,
            'referrer_id' => $referral->referrer_id,
            'lead_id' => $referral->lead_id,
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
