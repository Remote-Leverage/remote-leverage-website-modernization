<?php

declare(strict_types=1);

namespace App\Domains\Referral\Data;

readonly class ReferrerData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $referralCode,
        public ?string $company = null,
        public ?string $password = null,
        public ?string $stripeAccountId = null,
        public string $status = 'pending',
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            referralCode: $data['referral_code'] ?? $data['referralCode'],
            company: $data['company'] ?? null,
            password: $data['password'] ?? null,
            stripeAccountId: $data['stripe_account_id'] ?? $data['stripeAccountId'] ?? null,
            status: $data['status'] ?? 'pending',
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'referral_code' => $this->referralCode,
            'company' => $this->company,
            'password' => $this->password,
            'stripe_account_id' => $this->stripeAccountId,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}
