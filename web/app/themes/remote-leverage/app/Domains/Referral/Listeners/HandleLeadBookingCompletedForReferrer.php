<?php

declare(strict_types=1);

namespace App\Domains\Referral\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;
use Illuminate\Support\Facades\Log;

class HandleLeadBookingCompletedForReferrer
{
    public function __construct(
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle completed lead booking: attribute to referrer if applicable, recording
     * a qualified Referral.
     *
     * Booking a call only qualifies the lead — it does not fulfill the referral and
     * does not earn a reward. Per legacy RL_Referral_Service::update_referral_status(),
     * a reward is only generated when the referral is later explicitly transitioned to
     * "fulfilled"/"rewarded" (the actual deal closing, via admin action or an
     * authenticated CRM webhook), which is a separate, human/ops-driven step. See
     * FulfillReferralAction.
     */
    public function handle(LeadBookingCompleted $event): void
    {
        $lead = $event->lead;

        if (! in_array($lead->source_type, ['referral_hub', 'partnership'], true) || empty($lead->source_id)) {
            // Lead not attributed to a referrer
            return;
        }

        try {
            // Find referrer by referral code or slug
            $referrer = Referrer::query()
                ->where('referral_code', $lead->source_id)
                ->first();

            $referrerName = $referrer ? $referrer->name : $lead->source_id;

            $referral = null;

            if ($referrer && strtolower($lead->email) !== strtolower($referrer->email)) {
                $referral = Referral::query()->updateOrCreate(
                    [
                        'referrer_id' => $referrer->id,
                        'lead_email' => $lead->email,
                    ],
                    [
                        'lead_name' => $lead->name,
                        'lead_phone' => $lead->phone ?? '',
                        'landing_page' => $lead->landing_url,
                        'source' => 'booking_completed',
                        'status' => 'qualified',
                    ]
                );
            }

            // Dual logging Stage 2: consumption write
            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCompleted',
                actorDomain: 'Referral',
                outcome: 'succeeded',
                description: "Matched booking to referrer '{$referrerName}', referral qualified [source: {$lead->source_type}:{$lead->source_id}]",
                payload: [
                    'referrer_id' => $referrer?->id,
                    'referral_id' => $referral?->id,
                    'referral_code' => $lead->source_id,
                    'source_type' => $lead->source_type,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadBookingCompletedForReferrer: Error attributing lead #{$lead->id}: ".$e->getMessage());

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCompleted',
                actorDomain: 'Referral',
                outcome: 'failed',
                description: 'Failed to record referrer attribution: '.$e->getMessage()
            );
        }
    }
}
