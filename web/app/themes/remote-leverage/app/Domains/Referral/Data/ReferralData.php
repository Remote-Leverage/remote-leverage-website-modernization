<?php

declare(strict_types=1);

namespace App\Domains\Referral\Data;

readonly class ReferralData
{
    public function __construct(
        public int $referrerId,
        public string $referralCode,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $landingUrl = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $convertedEmail = null,
        public string $status = 'clicked',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            referrerId: (int) $data['referrer_id'],
            referralCode: $data['referral_code'],
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            landingUrl: $data['landing_url'] ?? null,
            utmSource: $data['utm_source'] ?? null,
            utmMedium: $data['utm_medium'] ?? null,
            utmCampaign: $data['utm_campaign'] ?? null,
            convertedEmail: $data['converted_email'] ?? null,
            status: $data['status'] ?? 'clicked',
        );
    }

    public function toArray(): array
    {
        return [
            'referrer_id' => $this->referrerId,
            'referral_code' => $this->referralCode,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'landing_url' => $this->landingUrl,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'converted_email' => $this->convertedEmail,
            'status' => $this->status,
        ];
    }
}
