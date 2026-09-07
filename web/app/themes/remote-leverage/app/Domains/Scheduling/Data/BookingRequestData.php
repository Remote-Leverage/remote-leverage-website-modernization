<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Data;

readonly class BookingRequestData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $startTime,
        public ?string $phone = null,
        public ?string $company = null,
        public ?string $notes = null,
        public string $timezone = 'UTC',
        public ?string $referralCode = null,
        public ?array $qualificationAnswers = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $utmTerm = null,
        public ?string $utmContent = null,
        public ?array $guestEmails = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            startTime: $data['start_time'] ?? $data['startTime'],
            phone: $data['phone'] ?? null,
            company: $data['company'] ?? null,
            notes: $data['notes'] ?? null,
            timezone: $data['timezone'] ?? 'UTC',
            referralCode: $data['referral_code'] ?? $data['referralCode'] ?? null,
            qualificationAnswers: $data['qualification_answers'] ?? $data['qualificationAnswers'] ?? null,
            utmSource: $data['utm_source'] ?? $data['utmSource'] ?? null,
            utmMedium: $data['utm_medium'] ?? $data['utmMedium'] ?? null,
            utmCampaign: $data['utm_campaign'] ?? $data['utmCampaign'] ?? null,
            utmTerm: $data['utm_term'] ?? $data['utmTerm'] ?? null,
            utmContent: $data['utm_content'] ?? $data['utmContent'] ?? null,
            guestEmails: $data['guest_emails'] ?? $data['guestEmails'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'start_time' => $this->startTime,
            'phone' => $this->phone,
            'company' => $this->company,
            'notes' => $this->notes,
            'timezone' => $this->timezone,
            'referral_code' => $this->referralCode,
            'qualification_answers' => $this->qualificationAnswers,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'utm_term' => $this->utmTerm,
            'utm_content' => $this->utmContent,
            'guest_emails' => $this->guestEmails,
        ];
    }
}
