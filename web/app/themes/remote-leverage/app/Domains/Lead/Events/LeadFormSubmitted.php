<?php

declare(strict_types=1);

namespace App\Domains\Lead\Events;

use App\Domains\Lead\Data\LeadCaptureData;

/**
 * Dispatched immediately upon form submission, BEFORE lead persistence.
 * Extension point for external rules, custom validations, and conditional enrichment.
 */
class LeadFormSubmitted
{
    public function __construct(
        public LeadCaptureData $data
    ) {}
}
