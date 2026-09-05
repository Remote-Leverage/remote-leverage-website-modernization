<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Data;

readonly class AnalyticsEventData
{
    public function __construct(
        public string $event,
        public string $distinctId,
        public array $properties = [],
        public ?int $timestamp = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            event: $data['event'] ?? $data['name'],
            distinctId: $data['distinct_id'] ?? $data['distinctId'] ?? 'anonymous',
            properties: $data['properties'] ?? [],
            timestamp: $data['timestamp'] ?? time(),
        );
    }

    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'distinct_id' => $this->distinctId,
            'properties' => $this->properties,
            'timestamp' => $this->timestamp,
        ];
    }
}
