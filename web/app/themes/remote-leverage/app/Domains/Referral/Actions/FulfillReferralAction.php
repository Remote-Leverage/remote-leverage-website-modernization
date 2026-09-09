<?php

declare(strict_types=1);

namespace App\Domains\Referral\Actions;

use App\Domains\Referral\Events\ReferralRecorded;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralReward;
use App\Domains\Referral\Services\ReferralSettingsService;
use Illuminate\Support\Facades\Event;

class FulfillReferralAction
{
    public function __construct(
        protected ReferralSettingsService $settings,
    ) {}

    /**
     * Generate the due reward for a referral once the underlying deal is fulfilled.
     *
     * Ported from legacy RL_Referral_Service::maybe_create_reward_for_referral(): a
     * referral earns its reward only when the deal itself closes — an explicit status
     * transition to "fulfilled"/"rewarded" (admin action or an authenticated CRM
     * webhook) — never merely from a lead booking a call. Idempotent: at most one
     * reward is ever created per referral.
     */
    public function execute(
        Referral $referral,
        ?float $customAmount = null,
        ?string $customCurrency = null,
        ?string $customDescription = null,
    ): ReferralReward {
        $defaults = $this->settings->get();

        $reward = ReferralReward::query()->firstOrCreate(
            ['referral_id' => $referral->id],
            [
                'referrer_id' => $referral->referrer_id,
                'reward_type' => $defaults['default_reward_type'],
                'amount' => $customAmount ?? $defaults['default_reward_amount'],
                'currency' => $customCurrency ?? $defaults['default_reward_currency'],
                'status' => 'due',
                'description' => $customDescription ?: "Reward for fulfilled deal #{$referral->id}",
                'created_at' => now(),
            ]
        );

        if ($reward->wasRecentlyCreated) {
            Event::dispatch(new ReferralRecorded($referral));
        }

        return $reward;
    }
}
