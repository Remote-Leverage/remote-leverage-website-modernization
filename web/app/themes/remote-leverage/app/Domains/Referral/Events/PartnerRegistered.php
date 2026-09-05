<?php

declare(strict_types=1);

namespace App\Domains\Referral\Events;

use App\Domains\Referral\Models\Partner;

class PartnerRegistered
{
    public function __construct(
        public Partner $partner
    ) {}
}
