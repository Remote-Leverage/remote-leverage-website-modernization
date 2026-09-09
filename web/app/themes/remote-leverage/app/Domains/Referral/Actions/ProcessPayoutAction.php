<?php

declare(strict_types=1);

namespace App\Domains\Referral\Actions;

use App\Domains\Referral\Data\PayoutData;
use App\Domains\Referral\Events\PayoutCompleted;
use App\Domains\Referral\Models\Payout;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\StripeConnectGateway;
use Illuminate\Support\Facades\Event;

class ProcessPayoutAction
{
    public function __construct(
        protected StripeConnectGateway $stripeGateway
    ) {}

    /**
     * Create and process a payout for a referrer.
     */
    public function execute(Referrer $referrer, float $amount, array $referralIds = [], ?string $notes = null): ?Payout
    {
        $payoutData = PayoutData::fromArray([
            'referrer_id' => $referrer->id,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'pending',
            'referral_ids' => $referralIds,
            'notes' => $notes,
        ]);

        $payout = Payout::query()->create($payoutData->toArray());

        $transferId = $this->stripeGateway->transferPayout($payout);
        if ($transferId) {
            Event::dispatch(new PayoutCompleted($payout));
        }

        return $payout;
    }
}
