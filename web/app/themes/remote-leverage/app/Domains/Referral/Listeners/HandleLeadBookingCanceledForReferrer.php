<?php

declare(strict_types=1);

namespace App\Domains\Referral\Listeners;

use App\Domains\Lead\Events\LeadBookingCanceled;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralReward;
use App\Domains\Referral\Models\Referrer;
use Illuminate\Support\Facades\Log;

class HandleLeadBookingCanceledForReferrer
{
    public function __construct(
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Reverse referrer attribution when a completed booking is later canceled.
     *
     * Normally there's nothing to un-reward here: booking a call only ever qualifies a
     * referral (see HandleLeadBookingCompletedForReferrer), and rewards aren't created
     * until the deal is separately marked fulfilled (FulfillReferralAction). This still
     * defensively handles the edge case where fulfillment happened before the
     * cancellation arrived: a still-due reward is removed, while an already-issued one
     * is left untouched (clawing back a completed payout is an ops decision, not an
     * automated one) and flagged in the activity log for manual review.
     */
    public function handle(LeadBookingCanceled $event): void
    {
        $lead = $event->lead;

        if (! in_array($lead->source_type, ['referral_hub', 'partnership'], true) || empty($lead->source_id)) {
            return;
        }

        try {
            $referrer = Referrer::query()->where('referral_code', $lead->source_id)->first();

            if (! $referrer) {
                return;
            }

            $referral = Referral::query()
                ->where('referrer_id', $referrer->id)
                ->where('lead_email', $lead->email)
                ->first();

            if (! $referral) {
                return;
            }

            $reward = ReferralReward::query()->where('referral_id', $referral->id)->first();
            $rewardOutcome = 'no_reward_existed';

            if ($reward && $reward->status === 'due') {
                $reward->delete();
                $rewardOutcome = 'due_reward_removed';
            } elseif ($reward && $reward->status === 'issued') {
                $rewardOutcome = 'already_issued_flagged_for_manual_review';
            }

            $referral->update(['status' => 'rejected']);

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCanceled',
                actorDomain: 'Referral',
                outcome: 'succeeded',
                description: "Reversed referrer attribution for canceled booking [referrer: {$referrer->name}, reward: {$rewardOutcome}]",
                payload: [
                    'referrer_id' => $referrer->id,
                    'referral_id' => $referral->id,
                    'reward_outcome' => $rewardOutcome,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadBookingCanceledForReferrer: Error reversing attribution for lead #{$lead->id}: ".$e->getMessage());

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCanceled',
                actorDomain: 'Referral',
                outcome: 'failed',
                description: 'Failed to reverse referrer attribution: '.$e->getMessage()
            );
        }
    }
}
