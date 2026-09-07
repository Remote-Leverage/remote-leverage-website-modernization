<?php

declare(strict_types=1);

namespace App\Domains\Lead\Events;

use App\Domains\Lead\Models\Lead;

/**
 * Dispatched when Scheduling successfully books the Calendly / Google Calendar consultation for a Lead.
 */
class LeadBookingCompleted
{
    public function __construct(
        public Lead $lead,
        public string $meetingId,
        public string $provider,
        public ?string $meetUrl = null,
        public ?string $startTime = null,
        public array $metadata = []
    ) {}
}
