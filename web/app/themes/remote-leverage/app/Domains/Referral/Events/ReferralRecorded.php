<?php

declare(strict_types=1);

namespace App\Domains\Referral\Events;

use App\Domains\Referral\Models\Referral;

class ReferralRecorded
{
    public function __construct(
        public Referral $referral
    ) {}
}
