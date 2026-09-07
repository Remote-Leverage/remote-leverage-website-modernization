<?php

declare(strict_types=1);

namespace App\Domains\Lead\Data;

readonly class LeadCaptureData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phone = null,
        public ?string $phoneCountry = null,
        public ?string $company = null,
        public ?string $roleNeeded = null,
        public ?string $weeklyHours = null,
        public ?string $monthlyRevenue = null,
        public ?string $startDate = null,
        public ?string $notes = null,
        public ?string $preferredSlot = null,
        public ?string $timezone = null,
        public ?string $referralCode = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $utmTerm = null,
        public ?string $utmContent = null,
        public ?string $gclid = null,
        public ?string $fbclid = null,
        public ?string $landingUrl = null,
        public ?string $referrerUrl = null,
        public ?string $sessionId = null,
        public array $extraData = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $firstName = $data['first_name'] ?? $data['firstName'] ?? null;
        $lastName = $data['last_name'] ?? $data['lastName'] ?? null;
        $name = (string) ($data['name'] ?? trim(($firstName ?? '').' '.($lastName ?? '')));

        return new self(
            name: $name,
            email: (string) ($data['email'] ?? ''),
            firstName: $firstName,
            lastName: $lastName,
            phone: isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null,
            phoneCountry: $data['phone_country'] ?? $data['phoneCountry'] ?? null,
            company: isset($data['company']) && $data['company'] !== '' ? (string) $data['company'] : null,
            roleNeeded: $data['role_needed'] ?? $data['roleNeeded'] ?? null,
            weeklyHours: $data['weekly_hours'] ?? $data['hoursPerWeek'] ?? null,
            monthlyRevenue: $data['monthly_revenue'] ?? $data['monthlyRevenue'] ?? null,
            startDate: $data['start_date'] ?? $data['startDate'] ?? null,
            notes: $data['notes'] ?? null,
            preferredSlot: $data['preferred_slot'] ?? $data['selectedSlot'] ?? null,
            timezone: $data['timezone'] ?? null,
            referralCode: $data['referral_code'] ?? $data['referralCode'] ?? $data['via'] ?? $data['ref'] ?? null,
            utmSource: $data['utm_source'] ?? $data['utmSource'] ?? null,
            utmMedium: $data['utm_medium'] ?? $data['utmMedium'] ?? null,
            utmCampaign: $data['utm_campaign'] ?? $data['utmCampaign'] ?? null,
            utmTerm: $data['utm_term'] ?? $data['utmTerm'] ?? null,
            utmContent: $data['utm_content'] ?? $data['utmContent'] ?? null,
            gclid: $data['gclid'] ?? null,
            fbclid: $data['fbclid'] ?? null,
            landingUrl: $data['landing_url'] ?? $data['landingUrl'] ?? null,
            referrerUrl: $data['referrer_url'] ?? $data['referrerUrl'] ?? null,
            sessionId: $data['session_id'] ?? $data['sessionId'] ?? null,
            extraData: $data['extra_data'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'phone_country' => $this->phoneCountry,
            'company' => $this->company,
            'role_needed' => $this->roleNeeded,
            'weekly_hours' => $this->weeklyHours,
            'monthly_revenue' => $this->monthlyRevenue,
            'start_date' => $this->startDate,
            'notes' => $this->notes,
            'preferred_slot' => $this->preferredSlot,
            'timezone' => $this->timezone,
            'referral_code' => $this->referralCode,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'utm_term' => $this->utmTerm,
            'utm_content' => $this->utmContent,
            'gclid' => $this->gclid,
            'fbclid' => $this->fbclid,
            'landing_url' => $this->landingUrl,
            'referrer_url' => $this->referrerUrl,
            'session_id' => $this->sessionId,
            'extra_data' => $this->extraData,
        ];
    }
}
