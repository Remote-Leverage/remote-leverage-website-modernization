<?php

declare(strict_types=1);

namespace App\Domains\Lead\Events;

use App\Domains\Lead\Models\Lead;

/**
 * Dispatched when a lead was created or initiated, but booking was abandoned before final confirmation.
 */
class LeadAbandoned
{
    public function __construct(
        public Lead $lead,
        public ?int $abandonedStep = null,
        public array $metadata = []
    ) {}
}
