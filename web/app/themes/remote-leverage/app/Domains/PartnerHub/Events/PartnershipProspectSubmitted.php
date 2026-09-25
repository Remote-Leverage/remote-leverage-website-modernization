<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Events;

use App\Domains\PartnerHub\Models\PartnershipProspect;

class PartnershipProspectSubmitted
{
    public function __construct(
        public PartnershipProspect $prospect
    ) {}
}
