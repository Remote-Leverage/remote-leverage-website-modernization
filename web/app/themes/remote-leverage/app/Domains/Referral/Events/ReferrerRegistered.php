<?php

declare(strict_types=1);

namespace App\Domains\Referral\Events;

use App\Domains\Referral\Models\Referrer;

class ReferrerRegistered
{
    public function __construct(
        public Referrer $referrer
    ) {}
}
