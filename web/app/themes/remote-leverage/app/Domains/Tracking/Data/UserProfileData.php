<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Data;

readonly class UserProfileData
{
    public function __construct(
        public string $identifier,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $referralCode = null,
        public ?array $traits = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            identifier: $data['identifier'] ?? $data['id'] ?? $data['email'],
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
            referralCode: $data['referral_code'] ?? null,
            traits: $data['traits'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'email' => $this->email,
            'name' => $this->name,
            'referral_code' => $this->referralCode,
            'traits' => $this->traits,
        ];
    }
}
