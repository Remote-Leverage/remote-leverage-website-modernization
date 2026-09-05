<?php

declare(strict_types=1);

namespace App\Domains\Referral\Actions;

use App\Domains\Referral\Data\PayoutData;
use App\Domains\Referral\Events\PayoutCompleted;
use App\Domains\Referral\Models\Partner;
use App\Domains\Referral\Models\Payout;
use App\Domains\Referral\Services\StripeConnectGateway;
use Illuminate\Support\Facades\Event;

class ProcessPayoutAction
{
    public function __construct(
        protected StripeConnectGateway $stripeGateway
    ) {}

    /**
     * Create and process a payout for an affiliate partner.
     */
    public function execute(Partner $partner, float $amount, array $referralIds = [], ?string $notes = null): ?Payout
    {
        $payoutData = PayoutData::fromArray([
            'partner_id' => $partner->id,
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
