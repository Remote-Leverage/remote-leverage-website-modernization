<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Data;

readonly class TimeSlotData
{
    public function __construct(
        public string $startTime,
        public string $endTime,
        public string $timezone,
        public bool $available = true,
        public ?string $consultantName = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            startTime: $data['start_time'] ?? $data['startTime'],
            endTime: $data['end_time'] ?? $data['endTime'],
            timezone: $data['timezone'] ?? 'UTC',
            available: (bool) ($data['available'] ?? true),
            consultantName: $data['consultant_name'] ?? $data['consultantName'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'timezone' => $this->timezone,
            'available' => $this->available,
            'consultant_name' => $this->consultantName,
            'metadata' => $this->metadata,
        ];
    }
}
