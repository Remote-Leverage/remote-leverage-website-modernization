<?php

declare(strict_types=1);

namespace App\Domains\Lead\Events;

use App\Domains\Lead\Models\Lead;

/**
 * Dispatched after the lead has been validated, stamped by AttributionEngine, and persisted in rl_leads.
 */
class LeadCreated
{
    public function __construct(
        public Lead $lead,
        public array $context = []
    ) {}
}
