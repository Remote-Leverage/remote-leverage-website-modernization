<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Data;

/**
 * One validated `/become-a-partner/` submission, in the shape `rl_partnership_prospects` stores.
 *
 * Built by SubmitPartnershipProspectAction only after validation passes, so everything here is
 * already trimmed, the email lowercased, and the three choice fields known slugs.
 */
readonly class PartnershipProspectData
{
    /**
     * @param  array<string, mixed>  $context  Attribution without a column of its own.
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $company,
        public string $role,
        public string $organizationType,
        public string $monthlyRevenue,
        public string $businessesReached,
        public ?string $message = null,
        public ?string $landingUrl = null,
        public ?string $referrerUrl = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $utmTerm = null,
        public ?string $utmContent = null,
        public ?string $ipAddress = null,
        public array $context = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            email: $data['email'],
            company: $data['company'],
            role: $data['role'],
            organizationType: $data['organization_type'],
            monthlyRevenue: $data['monthly_revenue'],
            businessesReached: $data['businesses_reached'],
            message: $data['message'] ?? null,
            landingUrl: $data['landing_url'] ?? null,
            referrerUrl: $data['referrer_url'] ?? null,
            utmSource: $data['utm_source'] ?? null,
            utmMedium: $data['utm_medium'] ?? null,
            utmCampaign: $data['utm_campaign'] ?? null,
            utmTerm: $data['utm_term'] ?? null,
            utmContent: $data['utm_content'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            context: $data['context'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'company' => $this->company,
            'role' => $this->role,
            'organization_type' => $this->organizationType,
            'monthly_revenue' => $this->monthlyRevenue,
            'businesses_reached' => $this->businessesReached,
            'message' => $this->message,
            'landing_url' => $this->landingUrl,
            'referrer_url' => $this->referrerUrl,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'utm_term' => $this->utmTerm,
            'utm_content' => $this->utmContent,
            'ip_address' => $this->ipAddress,
            'context' => $this->context === [] ? null : $this->context,
        ];
    }
}
