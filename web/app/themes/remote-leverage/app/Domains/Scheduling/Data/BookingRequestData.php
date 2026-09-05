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
        ];
    }
}
