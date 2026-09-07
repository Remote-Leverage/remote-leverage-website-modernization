<?php

declare(strict_types=1);

namespace App\Domains\Lead\Events;

use App\Domains\Lead\Models\Lead;

/**
 * Dispatched when a previously completed booking is canceled via Calendly/Google webhook.
 */
class LeadBookingCanceled
{
    public function __construct(
        public Lead $lead,
        public string $reason = 'Invitee canceled',
        public ?string $canceledBy = null,
        public array $metadata = []
    ) {}
}
