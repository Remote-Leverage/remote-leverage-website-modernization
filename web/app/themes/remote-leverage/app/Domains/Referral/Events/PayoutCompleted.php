<?php

declare(strict_types=1);

namespace App\Domains\Referral\Events;

use App\Domains\Referral\Models\Payout;

class PayoutCompleted
{
    public function __construct(
        public Payout $payout
    ) {}
}
