<?php

declare(strict_types=1);

namespace App\Domains\Referral\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Referral\Events\ReferralRecorded;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralReward;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralSettingsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class HandleLeadBookingCompletedForReferrer
{
    public function __construct(
        protected LeadActivityLogger $activityLogger,
        protected ReferralSettingsService $settings,
    ) {}

    /**
     * Handle completed lead booking: attribute to referrer if applicable,
     * recording a fulfilled Referral and auto-generating its due ReferralReward.
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
            $rewardCreated = false;

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
                        'status' => 'fulfilled',
                    ]
                );

                $rewardDefaults = $this->settings->get();

                $reward = ReferralReward::query()->firstOrCreate(
                    ['referral_id' => $referral->id],
                    [
                        'referrer_id' => $referrer->id,
                        'reward_type' => $rewardDefaults['default_reward_type'],
                        'amount' => $rewardDefaults['default_reward_amount'],
                        'currency' => $rewardDefaults['default_reward_currency'],
                        'status' => 'due',
                        'description' => "Auto-generated reward for referred booking (referral #{$referral->id})",
                        'created_at' => now(),
                    ]
                );

                $rewardCreated = $reward->wasRecentlyCreated;

                Event::dispatch(new ReferralRecorded($referral));
            }

            // Dual logging Stage 2: consumption write
            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCompleted',
                actorDomain: 'Referral',
                outcome: 'succeeded',
                description: "Matched booking to referrer '{$referrerName}' [source: {$lead->source_type}:{$lead->source_id}]",
                payload: [
                    'referrer_id' => $referrer?->id,
                    'referral_id' => $referral?->id,
                    'reward_created' => $rewardCreated,
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
