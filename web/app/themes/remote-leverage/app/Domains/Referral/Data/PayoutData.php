<?php

declare(strict_types=1);

namespace App\Domains\Referral\Data;

readonly class PayoutData
{
    public function __construct(
        public int $referrerId,
        public float $amount,
        public string $currency = 'USD',
        public ?string $stripeTransferId = null,
        public string $status = 'pending',
        public ?array $referralIds = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            referrerId: (int) $data['referrer_id'],
            amount: (float) $data['amount'],
            currency: $data['currency'] ?? 'USD',
            stripeTransferId: $data['stripe_transfer_id'] ?? null,
            status: $data['status'] ?? 'pending',
            referralIds: $data['referral_ids'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'referrer_id' => $this->referrerId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'stripe_transfer_id' => $this->stripeTransferId,
            'status' => $this->status,
            'referral_ids' => $this->referralIds,
            'notes' => $this->notes,
        ];
    }
}
