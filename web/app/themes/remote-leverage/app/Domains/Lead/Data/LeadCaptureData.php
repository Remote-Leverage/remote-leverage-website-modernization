<?php

declare(strict_types=1);

namespace App\Domains\Lead\Data;

readonly class LeadCaptureData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?string $company = null,
        public ?string $roleNeeded = null,
        public ?string $weeklyHours = null,
        public ?string $startDate = null,
        public ?string $notes = null,
        public ?string $preferredSlot = null,
        public ?string $timezone = null,
        public ?string $referralCode = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $landingUrl = null,
        public array $extraData = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            phone: isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null,
            company: isset($data['company']) && $data['company'] !== '' ? (string) $data['company'] : null,
            roleNeeded: $data['role_needed'] ?? $data['roleNeeded'] ?? null,
            weeklyHours: $data['weekly_hours'] ?? $data['hoursPerWeek'] ?? null,
            startDate: $data['start_date'] ?? $data['startDate'] ?? null,
            notes: $data['notes'] ?? null,
            preferredSlot: $data['preferred_slot'] ?? $data['selectedSlot'] ?? null,
            timezone: $data['timezone'] ?? null,
            referralCode: $data['referral_code'] ?? $data['referralCode'] ?? null,
            utmSource: $data['utm_source'] ?? null,
            utmMedium: $data['utm_medium'] ?? null,
            utmCampaign: $data['utm_campaign'] ?? null,
            landingUrl: $data['landing_url'] ?? null,
            extraData: $data['extra_data'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'role_needed' => $this->roleNeeded,
            'weekly_hours' => $this->weeklyHours,
            'start_date' => $this->startDate,
            'notes' => $this->notes,
            'preferred_slot' => $this->preferredSlot,
            'timezone' => $this->timezone,
            'referral_code' => $this->referralCode,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'landing_url' => $this->landingUrl,
            'extra_data' => $this->extraData,
        ];
    }
}
